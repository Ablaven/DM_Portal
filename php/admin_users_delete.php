<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_auth_schema.php';

auth_require_roles(['admin'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id is required.']);
    exit;
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    // Prevent deleting yourself.
    $me = auth_current_user();
    if ($me && (int)$me['user_id'] === $userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You cannot delete your own account.']);
        exit;
    }

    // Prevent deleting the last admin.
    $roleStmt = $pdo->prepare('SELECT role FROM portal_users WHERE user_id = :id');
    $roleStmt->execute([':id' => $userId]);
    $role = $roleStmt->fetchColumn();
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    if ($role === 'admin') {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM portal_users WHERE role='admin' AND is_active=1")->fetchColumn();
        if ($cnt <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete the last active admin account.']);
            exit;
        }
    }

    $stmt = $pdo->prepare('DELETE FROM portal_users WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);

    echo json_encode(['success' => true, 'data' => ['deleted' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete user.']);
}
