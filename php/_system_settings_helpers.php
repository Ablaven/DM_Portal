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

/**
 * Get all available pages grouped by role
 * @return array<string, array<string>>
 */
function dmportal_get_available_pages_by_role(): array
{
    return [
        'teacher' => [
            'attendance.php' => 'Attendance',
            'schedule_builder.php' => 'Schedule Builder',
            'evaluation.php' => 'Evaluation/Grading',
            'lectures.php' => 'Lecture Materials',
            'doctor.php' => 'Doctor Profile',
            'availability.php' => 'Availability Management',
            'hours_report.php' => 'Hours Reports',
            'hours_report_detail.php' => 'Hours Report Detail',
        ],
        'management' => [
            'attendance.php' => 'Attendance',
            'schedule_builder.php' => 'Schedule Builder',
            'evaluation.php' => 'Evaluation/Grading',
            'evaluation_reports.php' => 'Evaluation Reports',
            'lectures.php' => 'Lecture Materials',
            'doctor.php' => 'Doctor Profile',
            'availability.php' => 'Availability Management',
            'hours_report.php' => 'Hours Reports',
            'hours_report_detail.php' => 'Hours Report Detail',
            'attendance_report.php' => 'Attendance Report',
            'admin_students.php' => 'Student Management',
            'admin_doctors.php' => 'Doctor Management',
            'admin_courses.php' => 'Course Management',
            'admin_users.php' => 'User Management',
        ],
        'student' => [
            'student_dashboard.php' => 'Student Dashboard',
            'lectures.php' => 'Lecture Materials',
        ],
    ];
}

/**
 * Check if a page is disabled for a specific role
 */
function dmportal_is_page_disabled(PDO $pdo, string $page, string $role): bool
{
    // Admin always has access
    if ($role === 'admin') {
        return false;
    }
    
    $key = "page_disabled_{$role}_{$page}";
    $value = dmportal_get_setting($pdo, $key, '0');
    return $value === '1';
}

/**
 * Enable or disable a page for a specific role
 */
function dmportal_set_page_access(PDO $pdo, string $page, string $role, bool $disabled): void
{
    $key = "page_disabled_{$role}_{$page}";
    $value = $disabled ? '1' : '0';
    dmportal_set_setting($pdo, $key, $value);
}

/**
 * Get all currently disabled pages grouped by role
 * @return array<string, array<string, string>>
 */
function dmportal_get_all_disabled_pages(PDO $pdo): array
{
    dmportal_ensure_system_settings_table($pdo);
    
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value 
         FROM system_settings 
         WHERE setting_key LIKE 'page_disabled_%' AND setting_value = '1'"
    );
    
    $disabled = [];
    $allPages = dmportal_get_available_pages_by_role();
    
    while ($row = $stmt->fetch()) {
        $key = (string)$row['setting_key'];
        // Format: page_disabled_{role}_{page}
        if (preg_match('/^page_disabled_([^_]+)_(.+)$/', $key, $matches)) {
            $role = $matches[1];
            $page = $matches[2];
            
            if (!isset($disabled[$role])) {
                $disabled[$role] = [];
            }
            
            $pageName = $allPages[$role][$page] ?? $page;
            $disabled[$role][$page] = $pageName;
        }
    }
    
    return $disabled;
}

/**
 * Get page access status for a specific page and role
 */
function dmportal_get_page_access_status(PDO $pdo, string $page, string $role): array
{
    $disabled = dmportal_is_page_disabled($pdo, $page, $role);
    $allPages = dmportal_get_available_pages_by_role();
    
    return [
        'page' => $page,
        'role' => $role,
        'page_name' => $allPages[$role][$page] ?? $page,
        'disabled' => $disabled,
        'enabled' => !$disabled,
        'status' => $disabled ? 'Disabled' : 'Enabled',
    ];
}
