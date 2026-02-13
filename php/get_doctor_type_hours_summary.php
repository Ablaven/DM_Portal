<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_schema_helpers.php';

// Admin dashboard access
auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_doctor_type_column($pdo);

    $year = isset($_GET['year_level']) ? (int)$_GET['year_level'] : null;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : null;

    $filters = [];
    $params = [];
    if ($year) {
        $filters[] = 'c.year_level = :year_level';
        $params[':year_level'] = $year;
    }
    if ($semester) {
        $filters[] = 'c.semester = :semester';
        $params[':semester'] = $semester;
    }

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    $rows = [];
    try {
        $sql = "
            SELECT
                d.doctor_id,
                d.doctor_type,
                c.course_id,
                COALESCE(c.total_hours, 0) AS course_hours,
                (SELECT COALESCE(SUM(ch.allocated_hours), 0)
                 FROM course_hours ch
                 WHERE ch.course_id = c.course_id) AS allocated_hours
            FROM courses c
            JOIN doctors d ON d.doctor_id = c.doctor_id
            $where
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
            throw $e;
        }
        $sql = "
            SELECT
                d.doctor_id,
                d.doctor_type,
                c.course_id,
                COALESCE(c.total_hours, 0) AS course_hours,
                0 AS allocated_hours
            FROM courses c
            JOIN doctors d ON d.doctor_id = c.doctor_id
            $where
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    if (!$rows) {
        echo json_encode(['success' => true, 'data' => ['egyptian' => null, 'french' => null]]);
        exit;
    }

    $summary = [
        'Egyptian' => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
        'French' => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
    ];

    $doneStmt = $pdo->prepare(
        "SELECT s.course_id, COUNT(*) AS done_slots
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         " . ($where ? ($where . ' AND') : 'WHERE') . " s.course_id IS NOT NULL
         GROUP BY s.course_id"
    );
    $doneStmt->execute($params);
    $doneRows = $doneStmt->fetchAll();
    $doneMap = [];
    foreach ($doneRows as $row) {
        $doneMap[(int)$row['course_id']] = (int)$row['done_slots'];
    }

    foreach ($rows as $row) {
        $type = ucfirst(strtolower((string)($row['doctor_type'] ?? 'Egyptian')));
        if (!in_array($type, ['Egyptian', 'French'], true)) {
            $type = 'Egyptian';
        }

        $courseId = (int)$row['course_id'];
        $courseHours = (float)($row['course_hours'] ?? 0);
        $allocated = (float)($row['allocated_hours'] ?? 0);
        $target = $allocated > 0 ? $allocated : $courseHours;
        $doneSlots = $doneMap[$courseId] ?? 0;
        $done = min($target, (float)$doneSlots);
        $remaining = max(0.0, $target - $done);

        $summary[$type]['allocated'] += $target;
        $summary[$type]['done'] += $done;
        $summary[$type]['remaining'] += $remaining;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'egyptian' => [
                'label' => 'Egyptian',
                'allocated_hours' => round($summary['Egyptian']['allocated'], 2),
                'done_hours' => round($summary['Egyptian']['done'], 2),
                'remaining_hours' => round($summary['Egyptian']['remaining'], 2),
            ],
            'french' => [
                'label' => 'French',
                'allocated_hours' => round($summary['French']['allocated'], 2),
                'done_hours' => round($summary['French']['done'], 2),
                'remaining_hours' => round($summary['French']['remaining'], 2),
            ],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch doctor type hours summary.']);
}
