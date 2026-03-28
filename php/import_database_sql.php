<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';

auth_require_login();
$u = auth_current_user();
$role = (string)($u['role'] ?? '');
if ($role !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden: admin access required.';
    exit;
}

if (!isset($_FILES['sql_file'])) {
    http_response_code(400);
    echo 'No SQL file uploaded.';
    exit;
}

$file = $_FILES['sql_file'];
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'Upload failed.';
    exit;
}

$tmpPath = (string)($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo 'Invalid upload.';
    exit;
}

$sql = file_get_contents($tmpPath);
if ($sql === false || trim($sql) === '') {
    http_response_code(400);
    echo 'SQL file is empty.';
    exit;
}

$pdo = get_pdo();
set_time_limit(0);

/**
 * Split SQL into statements, respecting strings and comments.
 * @return list<string>
 */
function dmportal_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                $prev = $i > 0 ? $sql[$i - 1] : '';
                if ($prev === "" || $prev === "\n" || $prev === "\r" || $prev === "\t" || $prev === ' ') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
        } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
        } elseif ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

$statements = dmportal_split_sql($sql);
if (!$statements) {
    http_response_code(400);
    echo 'No SQL statements found.';
    exit;
}

$replaceExisting = isset($_POST['replace_existing']) && (string)$_POST['replace_existing'] === '1';

/**
 * Parse `CREATE TABLE`/`CREATE TABLE IF NOT EXISTS` for a bare table name (backtick or unquoted).
 */
function dmportal_create_table_name_from_statement(string $statement): ?string
{
    $s = trim($statement);
    if (!preg_match('/^CREATE\s+TABLE\s+/i', $s)) {
        return null;
    }
    if (preg_match('/^CREATE\s+TEMPORARY\s+TABLE\s+/i', $s)) {
        return null;
    }
    $s = (string)preg_replace('/^CREATE\s+TABLE\s+/i', '', $s);
    $s = trim($s);
    $s = (string)preg_replace('/^IF\s+NOT\s+EXISTS\s+/i', '', $s);
    $s = trim($s);
    if (preg_match('/^`([^`]+)`/', $s, $m)) {
        return $m[1];
    }
    if (preg_match('/^([a-zA-Z0-9_]+)/', $s, $m)) {
        return $m[1];
    }

    return null;
}

$errors = [];
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($statements as $statement) {
        try {
            if ($replaceExisting) {
                $tbl = dmportal_create_table_name_from_statement($statement);
                if ($tbl !== null && $tbl !== '') {
                    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $tbl) . '`');
                }
            }
            $pdo->exec($statement);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            break;
        }
    }
} finally {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $_) {
        // ignore
    }
}

if ($errors) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    $hint = '';
    if (stripos($errors[0], 'already exists') !== false && !$replaceExisting) {
        $hint = "\n\nTip: Check \"Replace existing tables\" on the Admin Panel import form "
            . 'when your .sql file has CREATE TABLE but no DROP (common for phpMyAdmin / schema-only dumps). '
            . 'Importing into an empty database does not require that option.';
    }
    echo "Import failed:\n" . $errors[0] . $hint;
    exit;
}

header('Location: ../admin_panel.php?import_status=success', true, 302);
exit;
