<?php
// Database connection (PDO)
// Update credentials to match your local environment.

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/_schema.php';

// Note: do NOT set Content-Type headers here.
// Each endpoint should set its own Content-Type (JSON, Excel export, etc.).

function get_pdo(): PDO
{
    $host = '127.0.0.1';
    // IMPORTANT: Your DB dump uses `digital_marketing_portal` (all lowercase).
    // On many MySQL/Linux setups, database names are case-sensitive.
    // You can override via environment variable DM_PORTAL_DB.
    $db   = dmportal_env('DM_PORTAL_DB', 'digital_marketing_portal');
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    dmportal_ensure_schema_version($pdo);
    return $pdo;
}
