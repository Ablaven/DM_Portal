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

$pdo = get_pdo();

set_time_limit(0);

$timestamp = gmdate('Ymd_His');
$filename = "digital_marketing_portal_{$timestamp}.sql";

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function dmportal_sql_escape_value(PDO $pdo, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return $pdo->quote((string)$value);
}

function dmportal_output(string $line): void
{
    echo $line;
}

// Header
$databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$databaseName = $databaseName !== '' ? $databaseName : 'database';

dmportal_output("-- Digital Marketing Portal SQL Export\n");
dmportal_output("-- Database: {$databaseName}\n");
dmportal_output("-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n\n");
dmportal_output("SET NAMES utf8mb4;\n");
dmportal_output("SET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_ASSOC);

foreach ($tables as $tableRow) {
    $table = (string)array_values($tableRow)[0];
    if ($table === '') {
        continue;
    }

    dmportal_output("-- ----------------------------------------\n");
    dmportal_output("-- Table structure for `{$table}`\n");
    dmportal_output("-- ----------------------------------------\n\n");
    dmportal_output("DROP TABLE IF EXISTS `{$table}`;\n");

    $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $createStmt['Create Table'] ?? '';
    if ($createSql !== '') {
        dmportal_output($createSql . ";\n\n");
    }

    $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
    $firstRow = $dataStmt->fetch(PDO::FETCH_ASSOC);
    if (!$firstRow) {
        dmportal_output("\n");
        continue;
    }

    $columns = array_keys($firstRow);
    $columnSql = implode(', ', array_map(fn($col) => "`{$col}`", $columns));

    dmportal_output("-- Dumping data for `{$table}`\n");

    $batch = [];
    $flushBatch = function () use (&$batch, $table, $columnSql): void {
        if (!$batch) {
            return;
        }
        dmportal_output("INSERT INTO `{$table}` ({$columnSql}) VALUES\n");
        dmportal_output(implode(",\n", $batch));
        dmportal_output(";\n\n");
        $batch = [];
    };

    $rowToValues = function (array $row) use ($pdo): string {
        $values = [];
        foreach ($row as $value) {
            $values[] = dmportal_sql_escape_value($pdo, $value);
        }
        return '(' . implode(', ', $values) . ')';
    };

    $batch[] = $rowToValues($firstRow);
    while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
        $batch[] = $rowToValues($row);
        if (count($batch) >= 200) {
            $flushBatch();
        }
    }
    $flushBatch();
}

dmportal_output("SET FOREIGN_KEY_CHECKS=1;\n");
