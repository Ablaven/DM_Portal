<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_activity_log_helpers.php';

auth_session_start();

// Log logout before destroying session
try {
    $pdo = get_pdo();
    $user = auth_current_user();
    
    if ($user) {
        dmportal_log_activity($pdo, 'logout', 'auth', [
            'targetType' => 'user',
            'targetName' => $user['username'],
            'targetId' => (string)$user['user_id'],
            'details' => ['role' => $user['role']],
            'result' => 'success',
            // IMPORTANT: Pass these explicitly from session before it's destroyed
            'userId' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ]);
    }
} catch (Throwable $e) {
    // Don't break logout if logging fails
    error_log('Logout logging error: ' . $e->getMessage());
}

unset($_SESSION['portal_user']);

echo json_encode(['success' => true]);

