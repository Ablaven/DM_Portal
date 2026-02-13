<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';

auth_require_login(true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        bad_request('year_level must be 1-3 or empty.');
    }
    if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
        bad_request('semester must be 1-2 or empty.');
    }

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);

    if ($role === 'teacher' && $doctorId <= 0) {
        echo json_encode(['success' => true, 'data' => ['metrics' => [], 'courses' => []]]);
        exit;
    }

    $courseWhere = [];
    $params = [];
    if ($yearLevel > 0) { $courseWhere[] = 'c.year_level = :year_level'; $params[':year_level'] = $yearLevel; }
    if ($semester > 0) { $courseWhere[] = 'c.semester = :semester'; $params[':semester'] = $semester; }
    if ($role === 'teacher' && $doctorId > 0) {
        $courseWhere[] = '(c.doctor_id = :doctor_id OR c.course_id IN (SELECT course_id FROM course_doctors WHERE doctor_id = :doctor_id))';
        $params[':doctor_id'] = $doctorId;
    }

    $whereSql = $courseWhere ? ('WHERE ' . implode(' AND ', $courseWhere)) : '';

    $coursesStmt = $pdo->prepare(
        "SELECT c.course_id, c.course_name, c.year_level, c.semester,
                GROUP_CONCAT(DISTINCT d.full_name ORDER BY d.full_name SEPARATOR ', ') AS doctor_names
         FROM courses c
         LEFT JOIN course_doctors cd ON cd.course_id = c.course_id
         LEFT JOIN doctors d ON d.doctor_id = cd.doctor_id
         $whereSql
         GROUP BY c.course_id
         ORDER BY c.year_level ASC, c.semester ASC, c.course_name ASC"
    );
    $coursesStmt->execute($params);
    $courses = $coursesStmt->fetchAll();

    $courseIds = array_map(fn($c) => (int)$c['course_id'], $courses);
    if (!$courseIds) {
        echo json_encode(['success' => true, 'data' => ['metrics' => [], 'courses' => []]]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

    $gradesSql =
        "SELECT g.course_id,
                AVG(g.final_score) AS avg_final,
                AVG(g.attendance_score) AS avg_attendance,
                COUNT(*) AS graded_count
         FROM evaluation_grades g
         WHERE g.course_id IN ($placeholders)";

    $gradesParams = $courseIds;

    if ($role === 'teacher' && $doctorId > 0) {
        $gradesSql .= " AND g.doctor_id = ?";
        $gradesParams[] = $doctorId;
    }

    $gradesSql .= " GROUP BY g.course_id";

    $gradesStmt = $pdo->prepare($gradesSql);
    $gradesStmt->execute($gradesParams);
    $gradeRows = $gradesStmt->fetchAll();

    $gradeMap = [];
    foreach ($gradeRows as $row) {
        $gradeMap[(int)$row['course_id']] = $row;
    }

    $summary = [];
    $totals = [
        'courses' => count($courseIds),
        'graded_students' => 0,
        'avg_final' => 0.0,
        'avg_attendance' => 0.0,
    ];
    $avgFinalBucket = [];
    $avgAttendBucket = [];

    foreach ($courses as $course) {
        $cid = (int)$course['course_id'];
        $grade = $gradeMap[$cid] ?? null;
        $avgFinal = $grade ? (float)$grade['avg_final'] : null;
        $avgAttendance = $grade ? (float)$grade['avg_attendance'] : null;
        $gradedCount = $grade ? (int)$grade['graded_count'] : 0;

        if ($avgFinal !== null) $avgFinalBucket[] = $avgFinal;
        if ($avgAttendance !== null) $avgAttendBucket[] = $avgAttendance;
        $totals['graded_students'] += $gradedCount;

        $summary[] = [
            'course_id' => $cid,
            'course_name' => (string)$course['course_name'],
            'year_level' => (int)$course['year_level'],
            'semester' => (int)$course['semester'],
            'doctor_names' => (string)($course['doctor_names'] ?? ''),
            'avg_final' => $avgFinal,
            'avg_attendance' => $avgAttendance,
            'graded_count' => $gradedCount,
        ];
    }

    $totals['avg_final'] = $avgFinalBucket ? round(array_sum($avgFinalBucket) / count($avgFinalBucket), 2) : 0.0;
    $totals['avg_attendance'] = $avgAttendBucket ? round(array_sum($avgAttendBucket) / count($avgAttendBucket), 2) : 0.0;

    echo json_encode([
        'success' => true,
        'data' => [
            'metrics' => $totals,
            'courses' => $summary,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation report summary.']);
}
