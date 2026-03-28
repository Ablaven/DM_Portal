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

// Default: wipe all tables/views in current DB, then run dump (avoids 1050 + errno 150). Opt out via admin_panel.
$replaceExisting = !isset($_POST['skip_drop_before_create']) || (string)$_POST['skip_drop_before_create'] !== '1';

/**
 * Drop every view and base table in the connection's current database (FOREIGN_KEY_CHECKS should be off).
 * Needed for full restores: dropping only tables that appear early in the dump (e.g. admins) leaves other
 * existing tables (portal_users, audit_log, …) with FK metadata pointing at the parent and can cause
 * errno 150 "Foreign key constraint is incorrectly formed" on CREATE TABLE.
 */
function dmportal_wipe_current_database(PDO $pdo): void
{
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($db === false || $db === null || (string)$db === '') {
        return;
    }
    $qSchema = $pdo->quote((string)$db);

    foreach (['VIEW', 'BASE TABLE'] as $tableType) {
        $qType = $pdo->quote($tableType);
        $stmt = $pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = {$qSchema} AND TABLE_TYPE = {$qType}"
        );
        if (!$stmt) {
            continue;
        }
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $name) {
            $ident = '`' . str_replace('`', '``', (string)$name) . '`';
            if ($tableType === 'VIEW') {
                $pdo->exec("DROP VIEW IF EXISTS {$ident}");
            } else {
                $pdo->exec("DROP TABLE IF EXISTS {$ident}");
            }
        }
    }
}

$errors = [];
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    if ($replaceExisting) {
        dmportal_wipe_current_database($pdo);
    }
    foreach ($statements as $statement) {
        try {
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
        $hint = "\n\nTip: Leave \"Skip DROP before CREATE\" unchecked (default). You checked it but your "
            . 'database already has tables — either import into an empty database or leave skip unchecked '
            . 'so each CREATE is preceded by DROP TABLE IF EXISTS.';
    } elseif (stripos($errors[0], 'errno: 150') !== false
        || stripos($errors[0], 'Foreign key constraint is incorrectly formed') !== false) {
        $hint = "\n\nTip: errno 150 often means leftover tables still had foreign keys to parents that were "
            . 'recreated. Leave \"Skip DROP\" unchecked (default): the importer clears all tables/views in '
            . 'this database first, then applies the dump.';
    }
    echo "Import failed:\n" . $errors[0] . $hint;
    exit;
}

header('Location: ../admin_panel.php?import_status=success', true, 302);
exit;
