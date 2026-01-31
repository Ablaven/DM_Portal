<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);

try {
    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $studentId = (int)($u['student_id'] ?? 0);

    if ($role !== 'student') {
        auth_require_roles(['student'], true);
    }

    if ($studentId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Student account missing student_id.']);
        exit;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT student_id, full_name, student_code FROM students WHERE student_id = :id');
    $stmt->execute([':id' => $studentId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student profile not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'student_id' => (int)$row['student_id'],
            'full_name' => (string)$row['full_name'],
            'student_code' => (string)($row['student_code'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch student profile.']);
}
