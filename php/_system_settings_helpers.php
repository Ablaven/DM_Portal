<?php

declare(strict_types=1);

/**
 * System settings helpers - manage global portal settings
 */

function dmportal_ensure_system_settings_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS system_settings (\n"
        ."  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,\n"
        ."  setting_value TEXT NOT NULL,\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Get a system setting value
 */
function dmportal_get_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        dmportal_ensure_system_settings_table($pdo);
        
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        
        return $row ? (string)$row['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Failed to get setting {$key}: " . $e->getMessage());
        return $default;
    }
}

/**
 * Set a system setting value
 */
function dmportal_set_setting(PDO $pdo, string $key, string $value): void
{
    dmportal_ensure_system_settings_table($pdo);
    
    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) 
         VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
}

/**
 * Check if attendance timeout is disabled
 */
function dmportal_is_attendance_timeout_disabled(PDO $pdo): bool
{
    $value = dmportal_get_setting($pdo, 'attendance_timeout_disabled', '0');
    return $value === '1';
}

/**
 * Get current attendance timeout status
 */
function dmportal_get_attendance_timeout_status(PDO $pdo): array
{
    dmportal_ensure_system_settings_table($pdo);
    
    $disabled = dmportal_is_attendance_timeout_disabled($pdo);
    
    return [
        'disabled' => $disabled,
        'enabled' => !$disabled,
        'status' => $disabled ? 'Disabled' : 'Enabled',
    ];
}
