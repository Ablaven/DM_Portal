<?php

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_term_helpers.php';

function dmportal_ensure_weeks_prep_column(PDO $pdo): void
{
    dmportal_ensure_schema_version($pdo);
    dmportal_ensure_terms_table($pdo);
    if (!dmportal_schema_column_exists($pdo, 'weeks', 'is_prep')) {
        $pdo->exec("ALTER TABLE weeks ADD COLUMN is_prep TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function dmportal_ensure_weeks_ramadan_column(PDO $pdo): void
{
    dmportal_ensure_schema_version($pdo);
    dmportal_ensure_terms_table($pdo);
    if (!dmportal_schema_column_exists($pdo, 'weeks', 'is_ramadan')) {
        $pdo->exec("ALTER TABLE weeks ADD COLUMN is_ramadan TINYINT(1) NOT NULL DEFAULT 0");
    }
}
