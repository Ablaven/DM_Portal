<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

auth_require_roles(['student'], true);

/** @var array{year_level:int,semester:int,program:string} */
$zeroedContext = ['year_level' => 0, 'semester' => 0, 'program' => ''];

try {
    $u = auth_current_user();
    $studentId = (int)($u['student_id'] ?? 0);

    // No student_id linked to this user - return zeroed context, not an error.
    if ($studentId <= 0) {
        echo json_encode(['success' => true, 'data' => $zeroedContext]);
        exit;
    }

    $pdo = get_pdo();
    dmportal_ensure_lecture_materials_table($pdo);

    $stmt = $pdo->prepare(
        'SELECT year_level, semester, program'
        . ' FROM students'
        . ' WHERE student_id = :student_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':student_id' => $studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Student row not found - return zeroed context.
    if ($row === false) {
        echo json_encode(['success' => true, 'data' => $zeroedContext]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'year_level' => (int)$row['year_level'],
            'semester'   => (int)$row['semester'],
            'program'    => (string)($row['program'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
