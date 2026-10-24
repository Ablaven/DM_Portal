<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

try {
    auth_require_login();
    
    $user = auth_current_user();
    
    echo json_encode([
        'success' => true,
        'data' => $user,
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get current user.',
    ]);
}
