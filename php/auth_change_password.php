<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_auth_schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

auth_require_login(true);

$current = (string)($_POST['current_password'] ?? '');
$new = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if ($new !== $confirm) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
    exit;
}

if (strlen($new) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
    exit;
}

try {
    $u = auth_current_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit;
    }

    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    $stmt = $pdo->prepare('SELECT user_id, password_hash FROM portal_users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$u['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    if (!password_verify($current, (string)$row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }

    $upd = $pdo->prepare('UPDATE portal_users SET password_hash = :h WHERE user_id = :id');
    $upd->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int)$u['user_id']]);

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update password.']);
}
