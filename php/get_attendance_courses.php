<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);

// Returns courses for the Attendance export dropdown.
// Filters:
// - year_level (required, 1-3)
// - week_id (required)
//
// Behavior:
// - Only courses that have at least one scheduled slot in the selected week.
// - Only courses matching the selected year_level.
// - Teachers only see courses that have slots belonging to their doctor_id.

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $yearLevel = (int)($_GET['year_level'] ?? 0);
    $weekId = (int)($_GET['week_id'] ?? 0);

    if ($yearLevel < 1 || $yearLevel > 3) bad_request('year_level must be 1-3.');
    if ($weekId <= 0) bad_request('week_id is required.');

    $pdo = get_pdo();

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');

    $sql =
        "SELECT DISTINCT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                c.course_type, c.subject_code
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         WHERE s.week_id = :week_id
           AND c.year_level = :year_level";

    $params = [':week_id' => $weekId, ':year_level' => $yearLevel];

    if ($role === 'teacher') {
        $doctorId = (int)($u['doctor_id'] ?? 0);
        if ($doctorId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account is missing doctor_id.']);
            exit;
        }
        $sql .= ' AND s.doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctorId;
    }

    $sql .= ' ORDER BY c.semester ASC, c.course_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance courses.']);
}
