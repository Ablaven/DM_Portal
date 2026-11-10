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
    
    // Auto-create unavailability table if it doesn't exist
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'unavailability'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE `unavailability` (
                  `unavailability_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `doctor_id` INT UNSIGNED NOT NULL,
                  `start_datetime` DATETIME NOT NULL,
                  `end_datetime` DATETIME NOT NULL,
                  `reason` VARCHAR(255) DEFAULT NULL,
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`unavailability_id`),
                  KEY `idx_doctor_dates` (`doctor_id`, `start_datetime`, `end_datetime`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Exception $e) {
        // Silently fail - table might already exist or user lacks permissions
        error_log("Auto-create unavailability table failed: " . $e->getMessage());
    }
    
    // Auto-create attendance_qr_sessions table if it doesn't exist
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'attendance_qr_sessions'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE `attendance_qr_sessions` (
                  `session_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `schedule_id` INT UNSIGNED NOT NULL,
                  `token` VARCHAR(64) NOT NULL,
                  `expires_at` DATETIME NOT NULL,
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`session_id`),
                  UNIQUE KEY `idx_schedule` (`schedule_id`),
                  KEY `idx_token_expires` (`token`, `expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Exception $e) {
        error_log("Auto-create attendance_qr_sessions table failed: " . $e->getMessage());
    }
    
    return $pdo;
}
