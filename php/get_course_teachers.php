<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);
auth_require_roles(['admin', 'management'], true);

try {
    $courseId = (int)($_GET['course_id'] ?? 0);
    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'course_id is required.']);
        exit;
    }

    $pdo = get_pdo();

    // Get all teachers assigned to this course
    $stmt = $pdo->prepare(
        'SELECT d.doctor_id, d.full_name
         FROM doctors d
         JOIN course_doctors cd ON cd.doctor_id = d.doctor_id
         WHERE cd.course_id = :course_id
         ORDER BY d.full_name ASC'
    );
    $stmt->execute([':course_id' => $courseId]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'teachers' => $teachers
        ]
    ]);
} catch (Throwable $e) {
    error_log("Error fetching course teachers: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch teachers.']);
}