<?php

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

function dmportal_ensure_weeks_prep_column(PDO $pdo): void
{
    dmportal_ensure_schema_version($pdo);
    if (!dmportal_schema_column_exists($pdo, 'weeks', 'is_prep')) {
        $pdo->exec("ALTER TABLE weeks ADD COLUMN is_prep TINYINT(1) NOT NULL DEFAULT 0");
    }
}
