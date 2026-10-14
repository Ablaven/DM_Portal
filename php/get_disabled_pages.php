<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_system_settings_helpers.php';

// Admin only
auth_require_roles(['admin']);

try {
    $pdo = get_pdo();
    
    $disabledPages = dmportal_get_all_disabled_pages($pdo);
    $availablePages = dmportal_get_available_pages_by_role();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'disabled_pages' => $disabledPages,
            'available_pages' => $availablePages,
        ],
    ]);
    
} catch (Exception $e) {
    error_log("Error getting disabled pages: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load page access data.',
    ]);
}
