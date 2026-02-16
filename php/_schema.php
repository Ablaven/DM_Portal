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

    $target = 4;

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

    if ($current < 3) {
        if (!dmportal_schema_column_exists($pdo, 'weeks', 'term_id')) {
            $pdo->exec("ALTER TABLE weeks ADD COLUMN term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec('CREATE INDEX idx_weeks_term_id ON weeks (term_id)');
        }

        if (!dmportal_schema_column_exists($pdo, 'evaluation_configs', 'term_id')) {
            $pdo->exec("ALTER TABLE evaluation_configs ADD COLUMN term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec('CREATE INDEX idx_eval_configs_term ON evaluation_configs (term_id, course_id)');
        }

        if (!dmportal_schema_column_exists($pdo, 'evaluation_grades', 'term_id')) {
            $pdo->exec("ALTER TABLE evaluation_grades ADD COLUMN term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec('CREATE INDEX idx_eval_grades_term ON evaluation_grades (term_id, course_id, doctor_id, student_id)');
        }

        if (!dmportal_schema_column_exists($pdo, 'attendance_records', 'term_id')) {
            $pdo->exec("ALTER TABLE attendance_records ADD COLUMN term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec('CREATE INDEX idx_attendance_term ON attendance_records (term_id, schedule_id)');
        }

        $pdo->prepare('UPDATE schema_versions SET version = 3 WHERE schema_name = :name')
            ->execute([':name' => $schemaName]);
        $current = 3;
    }

    if ($current < 4) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS academic_years ('
            . '  academic_year_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '  label VARCHAR(50) NOT NULL,'
            . '  status ENUM(\'active\',\'closed\') NOT NULL DEFAULT \'closed\','
            . '  start_date DATE DEFAULT NULL,'
            . '  end_date DATE DEFAULT NULL,'
            . '  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '  PRIMARY KEY (academic_year_id),'
            . '  UNIQUE KEY uq_academic_year_label (label),'
            . '  KEY idx_academic_year_status (status)'
            . ') ENGINE=InnoDB'
        );

        if (!dmportal_schema_column_exists($pdo, 'terms', 'academic_year_id')) {
            $pdo->exec("ALTER TABLE terms ADD COLUMN academic_year_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec('CREATE INDEX idx_terms_academic_year ON terms (academic_year_id)');
        }

        $pdo->exec("INSERT IGNORE INTO academic_years (academic_year_id, label, status) VALUES (1, '2025-2026', 'active')");

        $pdo->prepare('UPDATE schema_versions SET version = 4 WHERE schema_name = :name')
            ->execute([':name' => $schemaName]);
        $current = 4;
    }
}
