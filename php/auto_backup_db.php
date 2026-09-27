<?php
/**
 * Automatic Database Backup Script
 * Exports the database to Desktop every 24 hours
 */

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'digital_marketing_portal';

// Auto-detect user's Desktop path
$desktopPath = getenv('USERPROFILE') . '\\Desktop\\';

// Fallback to common path if env var not available
if (empty(getenv('USERPROFILE'))) {
    $desktopPath = 'C:\\Users\\' . get_current_user() . '\\Desktop\\';
}

$backupFileName = 'digital_marketing_portal_backup_' . date('Y-m-d_H-i-s') . '.sql';
$backupFilePath = $desktopPath . $backupFileName;

// Auto-detect mysqldump path
$mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Check if mysqldump exists
if (!file_exists($mysqldumpPath)) {
    error_log("Backup failed: mysqldump not found at $mysqldumpPath");
    exit(1);
}

// Execute backup
$command = sprintf(
    '"%s" --user=%s --password=%s --host=%s %s > "%s"',
    $mysqldumpPath,
    $dbUser,
    $dbPass,
    $dbHost,
    $dbName,
    $backupFilePath
);

exec($command, $output, $returnCode);

if ($returnCode === 0 && file_exists($backupFilePath)) {
    error_log("Database backup successful: $backupFilePath");
    
    // Delete backups older than 7 days to save space
    $files = glob($desktopPath . 'digital_marketing_portal_backup_*.sql');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 7 * 24 * 60 * 60) { // 7 days
                unlink($file);
                error_log("Deleted old backup: $file");
            }
        }
    }
    
    exit(0);
} else {
    error_log("Backup failed with return code: $returnCode");
    exit(1);
}
