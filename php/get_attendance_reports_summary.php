<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';

auth_require_login(true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;

    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        bad_request('year_level must be 1-3 or empty.');
    }

    $pdo = get_pdo();
    dmportal_ensure_attendance_records_table($pdo);

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);

    if ($role === 'teacher' && $doctorId <= 0) {
        echo json_encode(['success' => true, 'data' => ['metrics' => [], 'courses' => []]]);
        exit;
    }

    $where = [];
    $params = [];
    if ($yearLevel > 0) { $where[] = 'c.year_level = :year_level'; $params[':year_level'] = $yearLevel; }
    if ($role === 'teacher' && $doctorId > 0) {
        $where[] = 's.doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctorId;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $summaryStmt = $pdo->prepare(
        "SELECT c.course_id, c.course_name, c.year_level,
                d.full_name AS doctor_name,
                COUNT(ar.attendance_id) AS total_records,
                SUM(CASE WHEN ar.status = 'PRESENT' THEN 1 ELSE 0 END) AS present_records
         FROM attendance_records ar
         JOIN doctor_schedules s ON s.schedule_id = ar.schedule_id
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         $whereSql
         GROUP BY c.course_id, c.course_name, c.year_level, d.full_name
         ORDER BY c.year_level ASC, c.course_name ASC"
    );
    $summaryStmt->execute($params);
    $rows = $summaryStmt->fetchAll();

    $courses = [];
    $totals = [
        'courses' => 0,
        'total_sessions' => 0,
        'present' => 0,
        'absent' => 0,
        'attendance_rate' => 0.0,
    ];

    $rateBucket = [];

    foreach ($rows as $row) {
        $total = (int)$row['total_records'];
        $present = (int)$row['present_records'];
        $absent = max(0, $total - $present);
        $rate = $total > 0 ? round(($present / $total) * 100, 2) : 0.0;
        $rateBucket[] = $rate;

        $courses[] = [
            'course_id' => (int)$row['course_id'],
            'course_name' => (string)$row['course_name'],
            'year_level' => (int)$row['year_level'],
            'doctor_name' => (string)$row['doctor_name'],
            'total_records' => $total,
            'present_records' => $present,
            'absent_records' => $absent,
            'attendance_rate' => $rate,
        ];

        $totals['total_sessions'] += $total;
        $totals['present'] += $present;
        $totals['absent'] += $absent;
    }

    $totals['courses'] = count($courses);
    $totals['attendance_rate'] = $rateBucket ? round(array_sum($rateBucket) / count($rateBucket), 2) : 0.0;

    echo json_encode([
        'success' => true,
        'data' => [
            'metrics' => $totals,
            'courses' => $courses,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance report summary.']);
}
