<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json');

// Admin only
auth_require_roles(['admin'], true);

try {
    $pdo = get_pdo();
    
    // Alter the table to add zip and rar to the ENUM
    $pdo->exec(
        "ALTER TABLE lecture_materials 
         MODIFY COLUMN file_type ENUM('pdf','pptx','zip','rar') NOT NULL"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Database schema updated successfully. ZIP and RAR file types are now supported.'
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
