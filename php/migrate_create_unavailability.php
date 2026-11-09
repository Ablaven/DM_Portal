<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';

// Only admins can run migrations
auth_require_roles(['admin']);

try {
    $pdo = get_pdo();
    
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'unavailability'");
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Table unavailability already exists']);
        exit;
    }
    
    // Create the table
    $sql = "
    CREATE TABLE IF NOT EXISTS `unavailability` (
      `unavailability_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `doctor_id` INT UNSIGNED NOT NULL,
      `start_datetime` DATETIME NOT NULL,
      `end_datetime` DATETIME NOT NULL,
      `reason` VARCHAR(255) DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`unavailability_id`),
      KEY `idx_doctor_dates` (`doctor_id`, `start_datetime`, `end_datetime`),
      CONSTRAINT `fk_unavailability_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Table unavailability created successfully'
    ]);
    
} catch (Exception $e) {
    error_log("migrate_create_unavailability error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Migration failed: ' . $e->getMessage()
    ]);
}
