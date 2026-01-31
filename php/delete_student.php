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

$studentId = (int)($_POST['student_id'] ?? 0);
if ($studentId <= 0) bad_request('student_id is required.');

try {
    $pdo = get_pdo();

    $chk = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
    $chk->execute([':id' => $studentId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found.']);
        exit;
    }

    $pdo->prepare('DELETE FROM students WHERE student_id = :id')->execute([':id' => $studentId]);

    echo json_encode(['success' => true, 'data' => ['deleted' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete student.']);
}
