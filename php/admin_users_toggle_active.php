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
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : -1;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id is required.']);
    exit;
}

if (!in_array($isActive, [0, 1], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'is_active must be 0 or 1.']);
    exit;
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    $stmt = $pdo->prepare('UPDATE portal_users SET is_active = :a WHERE user_id = :id');
    $stmt->execute([':a' => $isActive, ':id' => $userId]);

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update user.']);
}
