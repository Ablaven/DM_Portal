<?php

declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_activity_log_helpers.php';

auth_require_roles(['admin'], true);

try {
    $pdo = get_pdo();
    
    // Get filters
    $filters = [];
    
    if (!empty($_GET['dateFrom'])) {
        $filters['dateFrom'] = trim((string)$_GET['dateFrom']);
    }
    
    if (!empty($_GET['dateTo'])) {
        $filters['dateTo'] = trim((string)$_GET['dateTo']);
    }
    
    if (!empty($_GET['username'])) {
        $filters['username'] = trim((string)$_GET['username']);
    }
    
    if (!empty($_GET['role']) && $_GET['role'] !== 'all') {
        $filters['role'] = trim((string)$_GET['role']);
    }
    
    if (!empty($_GET['actionType']) && $_GET['actionType'] !== 'all') {
        $filters['actionType'] = trim((string)$_GET['actionType']);
    }
    
    if (!empty($_GET['actionCategory']) && $_GET['actionCategory'] !== 'all') {
        $filters['actionCategory'] = trim((string)$_GET['actionCategory']);
    }
    
    if (!empty($_GET['result']) && $_GET['result'] !== 'all') {
        $filters['result'] = trim((string)$_GET['result']);
    }
    
    if (!empty($_GET['search'])) {
        $filters['search'] = trim((string)$_GET['search']);
    }
    
    // Pagination
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $filters['limit'] = $limit;
    $filters['offset'] = $offset;
    
    // Get logs and count
    $logs = dmportal_get_activity_logs($pdo, $filters);
    $total = dmportal_get_activity_logs_count($pdo, $filters);
    
    // Get filter options
    $actionTypes = dmportal_get_activity_action_types($pdo);
    $actionCategories = dmportal_get_activity_action_categories($pdo);
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'total' => $total,
        'filterOptions' => [
            'actionTypes' => $actionTypes,
            'actionCategories' => $actionCategories,
        ],
    ]);
    
} catch (Throwable $e) {
    error_log('Error in get_activity_logs.php: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load activity logs.']);
}
