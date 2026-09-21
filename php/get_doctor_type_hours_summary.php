<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_schema_helpers.php';
require_once __DIR__ . '/_course_hours_helpers.php';
require_once __DIR__ . '/_attendance_session_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_doctor_type_column($pdo);
    dmportal_ensure_attendance_sessions_table($pdo);

    $activeTermId = dmportal_get_active_term_id($pdo);

    $year     = isset($_GET['year_level']) ? (int)$_GET['year_level'] : null;
    $semester = isset($_GET['semester'])   ? (int)$_GET['semester']   : null;

    $filters = [];
    $params  = [];
    if ($year) {
        $filters[] = 'c.year_level = :year_level';
        $params[':year_level'] = $year;
    }
    if ($semester) {
        $filters[] = 'c.semester = :semester';
        $params[':semester'] = $semester;
    }
    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    $touchTable = function (string $table) use ($pdo): bool {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($stmt) { $stmt->fetch(PDO::FETCH_NUM); $stmt->closeCursor(); }
            return true;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1146) return false;
            throw $e;
        }
    };

    $hasCourseDoctors     = $touchTable('course_doctors');
    $hasCourseDoctorHours = $touchTable('course_doctor_hours');

    if (!$hasCourseDoctors) {
        echo json_encode(['success' => true, 'data' => ['egyptian' => null, 'french' => null]]);
        exit;
    }

    $hJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c.course_id AND h.doctor_id = cd.doctor_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, NULL AS doctor_id, NULL AS allocated_hours) h ON 1=0';

    $allocJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

    // Allocated hours per (course_id, doctor_id)
    $sql = "
        SELECT
          cd.course_id,
          cd.doctor_id,
          d.doctor_type,
          CASE
            WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
            ELSE (COALESCE(c.total_hours, 0) / GREATEST(cnt.cnt, 1))
          END AS allocated_hours
        FROM course_doctors cd
        JOIN courses c   ON c.course_id = cd.course_id
        JOIN doctors d   ON d.doctor_id = cd.doctor_id
        JOIN (
          SELECT course_id, COUNT(*) AS cnt
          FROM course_doctors
          GROUP BY course_id
        ) cnt ON cnt.course_id = c.course_id
        $hJoin
        $allocJoin
        $where
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        echo json_encode(['success' => true, 'data' => ['egyptian' => null, 'french' => null]]);
        exit;
    }

    $summary = [
        'Egyptian' => ['allocated' => 0.0, 'assigned' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
        'French'   => ['allocated' => 0.0, 'assigned' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
    ];

    // WHERE clause rewritten to alias cx for scheduled-slot queries
    $sWhere  = $filters
        ? ('WHERE ' . implode(' AND ', array_map(fn($f) => str_replace('c.', 'cx.', $f), $filters)) . ' AND')
        : 'WHERE';

    $sParams = $params;
    $sParams[':term_id'] = $activeTermId;

    // Done hours per (course_id, doctor_id) — sessions with hours_counted=1
    $doneStmt = $pdo->prepare(
        "SELECT s.course_id,
                s.doctor_id,
                COUNT(*) AS done_slots,
                SUM(COALESCE(s.extra_minutes, 0)) AS done_extra_minutes
         FROM doctor_schedules s
         JOIN weeks w_h ON w_h.week_id = s.week_id
         JOIN attendance_sessions ash
           ON ash.term_id = w_h.term_id AND ash.schedule_id = s.schedule_id AND ash.hours_counted = 1
         JOIN courses cx ON cx.course_id = s.course_id
         $sWhere s.course_id IS NOT NULL
           AND s.counts_towards_hours = 1
           AND w_h.term_id = :term_id
         GROUP BY s.course_id, s.doctor_id"
    );
    $doneStmt->execute($sParams);
    $doneMap = [];
    foreach ($doneStmt->fetchAll() as $r) {
        $doneMap[(int)$r['course_id'] . '_' . (int)$r['doctor_id']] = [
            'slots'         => (int)$r['done_slots'],
            'extra_minutes' => (int)$r['done_extra_minutes'],
        ];
    }

    // Assigned hours per (course_id, doctor_id) — all scheduled slots this term
    $assignedStmt = $pdo->prepare(
        "SELECT s.course_id,
                s.doctor_id,
                COUNT(*) * 1.5 + SUM(COALESCE(s.extra_minutes, 0)) / 60 AS assigned_hours
         FROM doctor_schedules s
         JOIN weeks w_a ON w_a.week_id = s.week_id
         JOIN courses cx ON cx.course_id = s.course_id
         $sWhere s.course_id IS NOT NULL
           AND s.counts_towards_hours = 1
           AND w_a.term_id = :term_id
         GROUP BY s.course_id, s.doctor_id"
    );
    $assignedStmt->execute($sParams);
    $assignedMap = [];
    foreach ($assignedStmt->fetchAll() as $r) {
        $assignedMap[(int)$r['course_id'] . '_' . (int)$r['doctor_id']] = (float)$r['assigned_hours'];
    }

    foreach ($rows as $row) {
        $type = ucfirst(strtolower((string)($row['doctor_type'] ?? 'Egyptian')));
        if (!in_array($type, ['Egyptian', 'French'], true)) {
            $type = 'Egyptian';
        }

        $key       = (int)$row['course_id'] . '_' . (int)$row['doctor_id'];
        $allocated = (float)($row['allocated_hours'] ?? 0);
        $doneData  = $doneMap[$key] ?? ['slots' => 0, 'extra_minutes' => 0];
        $done      = min($allocated, ((float)$doneData['slots'] * 1.5) + ((float)$doneData['extra_minutes'] / 60));
        $remaining = max(0.0, $allocated - $done);
        $assigned  = $assignedMap[$key] ?? 0.0;

        $summary[$type]['allocated'] += $allocated;
        $summary[$type]['assigned']  += $assigned;
        $summary[$type]['done']      += $done;
        $summary[$type]['remaining'] += $remaining;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'egyptian' => [
                'label'           => 'Egyptian',
                'allocated_hours' => round($summary['Egyptian']['allocated'], 2),
                'assigned_hours'  => round($summary['Egyptian']['assigned'],  2),
                'done_hours'      => round($summary['Egyptian']['done'],      2),
                'remaining_hours' => round($summary['Egyptian']['remaining'], 2),
            ],
            'french' => [
                'label'           => 'French',
                'allocated_hours' => round($summary['French']['allocated'], 2),
                'assigned_hours'  => round($summary['French']['assigned'],  2),
                'done_hours'      => round($summary['French']['done'],      2),
                'remaining_hours' => round($summary['French']['remaining'], 2),
            ],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch doctor type hours summary.']);
}
