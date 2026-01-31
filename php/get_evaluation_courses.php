<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';

auth_require_login(true);

try {
    $pdo = get_pdo();

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);

    if ($role === 'teacher') {
        if ($doctorId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account missing doctor_id.']);
            exit;
        }
    }

    $courses = [];
    $params = [];

    if ($role === 'teacher') {
        $sql =
            "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                    c.course_type, c.subject_code
             FROM courses c
             WHERE c.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctorId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll();

        // Try merging course_doctors assignments if table exists.
        try {
            $sql2 =
                "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                        c.course_type, c.subject_code
                 FROM courses c
                 JOIN course_doctors cd ON cd.course_id = c.course_id AND cd.doctor_id = :doctor_id";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute($params);
            $courses = array_merge($courses, $stmt2->fetchAll());
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
        }

        // De-duplicate by course_id
        $unique = [];
        foreach ($courses as $row) {
            $unique[(int)$row['course_id']] = $row;
        }
        $courses = array_values($unique);

        usort($courses, fn($a, $b) => [$a['year_level'], $a['course_name']] <=> [$b['year_level'], $b['course_name']]);

        echo json_encode(['success' => true, 'data' => $courses]);
        exit;
    }

    $sql =
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                c.course_type, c.subject_code
         FROM courses c
         ORDER BY c.year_level ASC, c.course_name ASC";

    $stmt = $pdo->query($sql);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation courses.']);
}
