<?php

declare(strict_types=1);

function dmportal_schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col');
    $stmt->execute([':table' => $table, ':col' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function dmportal_ensure_schema_version(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_versions ('
        . '  schema_name VARCHAR(64) NOT NULL PRIMARY KEY,'
        . '  version INT NOT NULL,'
        . '  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB'
    );

    $schemaName = 'dmportal';
    $stmt = $pdo->prepare('SELECT version FROM schema_versions WHERE schema_name = :name');
    $stmt->execute([':name' => $schemaName]);
    $row = $stmt->fetch();
    $current = $row ? (int)$row['version'] : 0;

    $target = 2;

    if ($current < 1) {
        $pdo->prepare('INSERT INTO schema_versions (schema_name, version) VALUES (:name, 1) ON DUPLICATE KEY UPDATE version = VALUES(version)')
            ->execute([':name' => $schemaName]);
        $current = 1;
    }

    if ($current < 2) {
        if (dmportal_schema_column_exists($pdo, 'courses', 'course_Hours') && !dmportal_schema_column_exists($pdo, 'courses', 'course_hours')) {
            $pdo->exec('ALTER TABLE courses CHANGE course_Hours course_hours DECIMAL(5,2) NOT NULL DEFAULT 10.00');
        }
        $pdo->prepare('UPDATE schema_versions SET version = 2 WHERE schema_name = :name')
            ->execute([':name' => $schemaName]);
        $current = 2;
    }
}
