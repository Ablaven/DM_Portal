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
    $page = trim((string)($_POST['page'] ?? ''));
    $role = trim((string)($_POST['role'] ?? ''));
    
    if (empty($page)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Page parameter is required']);
        exit;
    }
    
    if (empty($role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Role parameter is required']);
        exit;
    }
    
    // Validate role
    if (!in_array($role, ['teacher', 'management', 'student'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid role. Must be teacher, management, or student']);
        exit;
    }
    
    // Validate page exists for this role
    $availablePages = dmportal_get_available_pages_by_role();
    if (!isset($availablePages[$role][$page])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid page for this role']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get current status
    $currentlyDisabled = dmportal_is_page_disabled($pdo, $page, $role);
    
    // Toggle it
    dmportal_set_page_access($pdo, $page, $role, !$currentlyDisabled);
    
    // Get new status for response
    $status = dmportal_get_page_access_status($pdo, $page, $role);
    
    $message = $status['disabled']
        ? "Page '{$status['page_name']}' has been disabled for {$role}s. They will no longer see it in navigation or be able to access it."
        : "Page '{$status['page_name']}' has been enabled for {$role}s. They can now access it normally.";
    
    echo json_encode([
        'success' => true,
        'page' => $status['page'],
        'role' => $status['role'],
        'page_name' => $status['page_name'],
        'disabled' => $status['disabled'],
        'enabled' => $status['enabled'],
        'status' => $status['status'],
        'message' => $message,
    ]);
    
} catch (Exception $e) {
    error_log("Error toggling page access: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update page access. Please try again.',
    ]);
}
