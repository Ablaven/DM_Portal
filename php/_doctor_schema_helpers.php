<?php

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

function dmportal_ensure_doctor_type_column(PDO $pdo): void
{
    dmportal_ensure_schema_version($pdo);
    if (!dmportal_schema_column_exists($pdo, 'doctors', 'doctor_type')) {
        $pdo->exec("ALTER TABLE doctors ADD COLUMN doctor_type VARCHAR(16) NOT NULL DEFAULT 'Egyptian'");
    }
}
