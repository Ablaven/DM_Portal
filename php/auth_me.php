<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';

$u = auth_current_user();
if (!$u) {
    echo json_encode(['success' => true, 'data' => ['authenticated' => false]]);
    exit;
}

$allowed = auth_allowed_pages_for_user($u);

echo json_encode([
    'success' => true,
    'data' => [
        'authenticated' => true,
        'user_id' => $u['user_id'],
        'username' => $u['username'],
        'role' => $u['role'],
        'doctor_id' => $u['doctor_id'],
        'student_id' => $u['student_id'],
        'allowed_pages' => $allowed,
    ],
]);
