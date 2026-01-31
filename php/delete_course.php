<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $message): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
if ($courseId <= 0) bad_request('course_id is required.');

try {
    $pdo = get_pdo();

    // Course exists?
    $chk = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = :id');
    $chk->execute([':id' => $courseId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found.']);
        exit;
    }

    // Refuse delete if scheduled anywhere (FK is RESTRICT anyway, but give friendly error)
    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM doctor_schedules WHERE course_id = :id');
    $cnt->execute([':id' => $courseId]);
    $row = $cnt->fetch();
    if ((int)($row['c'] ?? 0) > 0) {
        bad_request('Cannot delete: course is used in schedule. Remove scheduled slots first.');
    }

    $del = $pdo->prepare('DELETE FROM courses WHERE course_id = :id');
    $del->execute([':id' => $courseId]);

    echo json_encode(['success' => true, 'data' => ['deleted' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete course.']);
}
