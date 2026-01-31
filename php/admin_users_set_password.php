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
$password = (string)($_POST['password'] ?? '');

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id is required.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    $chk = $pdo->prepare('SELECT user_id FROM portal_users WHERE user_id = :id');
    $chk->execute([':id' => $userId]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE portal_users SET password_hash = :h WHERE user_id = :id');
    $stmt->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to set password.']);
}
