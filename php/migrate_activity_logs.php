<?php

declare(strict_types=1);

/**
 * Migration script to add response_time_ms column to activity_logs table
 * Run this once to update the database schema
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_activity_log_helpers.php';

try {
    $pdo = get_pdo();
    
    echo "Running activity_logs table migration...\n\n";
    
    // This will create the table if it doesn't exist, or add the column if it's missing
    dmportal_ensure_activity_logs_table($pdo);
    
    echo "✅ Migration complete!\n";
    echo "The response_time_ms column has been added to the activity_logs table.\n\n";
    echo "New activity logs will now track request duration.\n";
    echo "Existing logs will show '—' for response time (no data available).\n\n";
    
} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
