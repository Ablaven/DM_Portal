<?php

declare(strict_types=1);

/**
 * Archive and Cleanup Activity Logs
 * 
 * This script:
 * 1. Exports all activity logs to Excel
 * 2. Saves to archive directory with timestamp
 * 3. Deletes logs older than 3 days from database
 * 
 * Run via scheduled task every 3 days at 10 PM
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_activity_log_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

try {
    $pdo = get_pdo();
    
    // Archive directory - save to Desktop like SQL backups
    $archiveDir = getenv('USERPROFILE') . '\\Desktop';
    if (!is_dir($archiveDir)) {
        // Fallback if Desktop doesn't exist (unlikely)
        $archiveDir = 'C:\\activity_logs_archive';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0777, true);
        }
    }
    
    // Get all logs (no filters, get everything before deletion)
    $filters = [
        'limit' => 1000000,
        'offset' => 0,
    ];
    
    $logs = dmportal_get_activity_logs($pdo, $filters);
    
    if (empty($logs)) {
        echo "No activity logs to archive.\n";
        exit(0);
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
    $filename = "activity_logs_archive_{$timestamp}.xlsx";
    $filepath = $archiveDir . '\\' . $filename;
    
    // Save to file
    $xlsxContent = $xlsx->downloadToString($filename);
    file_put_contents($filepath, $xlsxContent);
    
    $logCount = count($logs);
    echo "✓ Archived {$logCount} activity logs to: {$filepath}\n";
    
    // Now delete logs older than 3 days
    $deletedCount = dmportal_delete_old_activity_logs($pdo, 3);
    
    echo "✓ Deleted {$deletedCount} old activity logs (older than 3 days)\n";
    echo "✓ Archive and cleanup completed successfully!\n";
    
    exit(0);
    
} catch (Throwable $e) {
    echo "✗ Error during archive/cleanup: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    error_log('Archive/cleanup error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
}
