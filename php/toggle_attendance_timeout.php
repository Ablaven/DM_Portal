<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_system_settings_helpers.php';

// Admin only
auth_require_roles(['admin']);

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get current status
    $currentlyDisabled = dmportal_is_attendance_timeout_disabled($pdo);
    
    // Toggle it
    $newValue = $currentlyDisabled ? '0' : '1';
    dmportal_set_setting($pdo, 'attendance_timeout_disabled', $newValue);
    
    // Get new status for response
    $status = dmportal_get_attendance_timeout_status($pdo);
    
    echo json_encode([
        'success' => true,
        'disabled' => $status['disabled'],
        'enabled' => $status['enabled'],
        'status' => $status['status'],
        'message' => $status['disabled'] 
            ? 'Attendance timeout enforcement disabled. Teachers can now take attendance at any time.' 
            : 'Attendance timeout enforcement enabled. Teachers must take attendance within 30 minutes after lecture ends.'
    ]);
    
} catch (Exception $e) {
    error_log("Error toggling attendance timeout: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update setting. Please try again.'
    ]);
}
