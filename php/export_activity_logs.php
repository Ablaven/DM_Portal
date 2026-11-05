<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_activity_log_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_roles(['admin'], true);

function bad_request(string $m): void {
    http_response_code(400);
    die($m);
}

try {
    $pdo = get_pdo();
    
    // Get filters (same as get_activity_logs.php)
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
    
    // No limit for export (get all matching records)
    $filters['limit'] = 100000;
    $filters['offset'] = 0;
    
    // Get logs
    $logs = dmportal_get_activity_logs($pdo, $filters);
    
    if (empty($logs)) {
        bad_request('No activity logs found with current filters.');
    }
    
    // Build Excel file
    $xlsx = new SimpleXlsxWriter();
    
    $rows = [];
    
    // Header row
    $rows[] = [
        'Log ID',
        'Date/Time',
        'Username',
        'Role',
        'Action Type',
        'Category',
        'Target Type',
        'Target ID',
        'Target Name',
        'Details',
        'Old Value',
        'New Value',
        'IP Address',
        'User Agent',
        'Page URL',
        'Result',
        'Error Message',
    ];
    
    // Data rows
    foreach ($logs as $log) {
        $rows[] = [
            $log['log_id'],
            $log['created_at'],
            $log['username'] ?? 'N/A',
            $log['role'] ?? 'N/A',
            $log['action_type'],
            $log['action_category'],
            $log['target_type'] ?? '',
            $log['target_id'] ?? '',
            $log['target_name'] ?? '',
            $log['details'] ?? '',
            $log['old_value'] ?? '',
            $log['new_value'] ?? '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['page_url'] ?? '',
            $log['result'],
            $log['error_message'] ?? '',
        ];
    }
    
    // Style map (header row gets header style)
    $styleMap = [];
    $headerStyle = $xlsx->styleHeader();
    
    for ($col = 0; $col < 17; $col++) {
        $styleMap[0][$col] = $headerStyle;
    }
    
    // Column widths
    $colWidths = [
        0 => 10,   // Log ID
        1 => 20,   // Date/Time
        2 => 15,   // Username
        3 => 12,   // Role
        4 => 20,   // Action Type
        5 => 15,   // Category
        6 => 15,   // Target Type
        7 => 12,   // Target ID
        8 => 25,   // Target Name
        9 => 40,   // Details
        10 => 30,  // Old Value
        11 => 30,  // New Value
        12 => 15,  // IP Address
        13 => 30,  // User Agent
        14 => 35,  // Page URL
        15 => 10,  // Result
        16 => 30,  // Error Message
    ];
    
    $xlsx->addSheet('Activity Logs', $rows, [
        'styleMap' => $styleMap,
        'colWidths' => $colWidths,
    ]);
    
    // Generate filename with timestamp
    $timestamp = date('Ymd_His');
    $filename = "activity_logs_{$timestamp}.xlsx";
    
    $xlsx->download($filename);
    exit;
    
} catch (Throwable $e) {
    error_log('Error in export_activity_logs.php: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    die('Failed to generate Excel file: ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
