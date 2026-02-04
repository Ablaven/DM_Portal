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

$errors = [];
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        break;
    }
}

if ($errors) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Import failed:\n" . $errors[0];
    exit;
}

header('Location: ../admin_panel.php?import_status=success', true, 302);
exit;
