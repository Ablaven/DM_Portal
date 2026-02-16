<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

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
    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    if ($role === 'teacher') {
        $params[':doctor_id'] = $doctorId;
        $params[':doctor_id2'] = $doctorId;

        try {
            $stmt = $pdo->prepare(
                "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                        c.course_type, c.subject_code,
                        c.doctor_id,
                        (SELECT GROUP_CONCAT(DISTINCT cd.doctor_id ORDER BY cd.doctor_id SEPARATOR ',')
                         FROM course_doctors cd
                         WHERE cd.course_id = c.course_id) AS doctor_ids
                 FROM courses c
                 WHERE c.doctor_id = :doctor_id
                    OR EXISTS (
                        SELECT 1
                        FROM course_doctors cd2
                        WHERE cd2.course_id = c.course_id AND cd2.doctor_id = :doctor_id2
                    )"
            );
            $stmt->execute($params);
            $courses = $stmt->fetchAll();
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if (!in_array($code, [1146, 1055], true)) {
                throw $e;
            }
            $stmt = $pdo->prepare(
                "SELECT course_id, course_name, program, year_level, semester,
                        course_type, subject_code, doctor_id,
                        NULL AS doctor_ids
                 FROM courses
                 WHERE doctor_id = :doctor_id"
            );
            $stmt->execute($params);
            $courses = $stmt->fetchAll();
        }

        if (!$courses) {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                        c.course_type, c.subject_code,
                        c.doctor_id,
                        NULL AS doctor_ids
                 FROM doctor_schedules s
                 JOIN courses c ON c.course_id = s.course_id
                 WHERE s.doctor_id = :doctor_id"
            );
            $stmt->execute($params);
            $courses = $stmt->fetchAll();
        }

        usort($courses, fn($a, $b) => [$a['year_level'], $a['course_name']] <=> [$b['year_level'], $b['course_name']]);

        echo json_encode(['success' => true, 'data' => $courses, 'term_id' => $termId]);
        exit;
    }

    $sql =
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                c.course_type, c.subject_code,
                c.doctor_id,
                GROUP_CONCAT(DISTINCT cd.doctor_id ORDER BY cd.doctor_id SEPARATOR ',') AS doctor_ids
         FROM courses c
         LEFT JOIN course_doctors cd ON cd.course_id = c.course_id
         GROUP BY c.course_id
         ORDER BY c.year_level ASC, c.course_name ASC";

    $stmt = $pdo->query($sql);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'term_id' => $termId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation courses.']);
}
