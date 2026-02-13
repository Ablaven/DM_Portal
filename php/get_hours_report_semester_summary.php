<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin', 'teacher'], true);

$user = auth_current_user();
$role = (string)($user['role'] ?? '');
$doctorScopeId = 0;
if ($role === 'teacher') {
    $doctorScopeId = (int)($user['doctor_id'] ?? 0);
    if ($doctorScopeId <= 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
}

try {
    $pdo = get_pdo();

    $touchTable = function (string $table) use ($pdo): bool {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($stmt) $stmt->fetch(PDO::FETCH_NUM);
            if ($stmt) $stmt->closeCursor();
            return true;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1146) return false;
            throw $e;
        }
    };

    if (!$touchTable('course_doctors')) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $hasCourseDoctorHours = $touchTable('course_doctor_hours');
    $hasWeekCancellations = $touchTable('doctor_week_cancellations');
    $hasSlotCancellations = $touchTable('doctor_slot_cancellations');
    $hasSchedules = $touchTable('doctor_schedules');

    $hJoin = $hasCourseDoctorHours ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c.course_id AND h.doctor_id = d.doctor_id' : 'LEFT JOIN (SELECT NULL AS course_id, NULL AS doctor_id, NULL AS allocated_hours) h ON 1=0';

    $weekCancelJoin = $hasWeekCancellations
        ? "LEFT JOIN doctor_week_cancellations cw\n            ON cw.week_id = s.week_id\n           AND cw.doctor_id = s.doctor_id\n           AND cw.day_of_week = s.day_of_week"
        : "LEFT JOIN (SELECT NULL AS cancellation_id, NULL AS week_id, NULL AS doctor_id, NULL AS day_of_week) cw ON 1=0";

    $slotCancelJoin = $hasSlotCancellations
        ? "LEFT JOIN doctor_slot_cancellations cs\n            ON cs.week_id = s.week_id\n           AND cs.doctor_id = s.doctor_id\n           AND cs.day_of_week = s.day_of_week\n           AND cs.slot_number = s.slot_number"
        : "LEFT JOIN (SELECT NULL AS slot_cancellation_id, NULL AS week_id, NULL AS doctor_id, NULL AS day_of_week, NULL AS slot_number) cs ON 1=0";

    $doneSubquery = $hasSchedules ? "
          SELECT
            s.doctor_id,
            s.course_id,
            COUNT(*) AS done_slots,
            SUM(COALESCE(s.extra_minutes,0)) AS done_extra_minutes
          FROM doctor_schedules s
          $weekCancelJoin
          $slotCancelJoin
          WHERE s.counts_towards_hours = 1
            AND cw.cancellation_id IS NULL
            AND cs.slot_cancellation_id IS NULL
          GROUP BY s.doctor_id, s.course_id
        " : "
          SELECT NULL AS doctor_id, NULL AS course_id, 0 AS done_slots
        ";

    $allocJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

    $where = [];
    if ($doctorScopeId > 0) { $where[] = 'd.doctor_id = :doctor_id'; }
    $courseFilterSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
          c.year_level,
          c.semester,
          CASE
            WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
            ELSE (COALESCE(c.total_hours, 0) / GREATEST(cd.cnt, 1))
          END AS allocated_hours,
          ROUND(COALESCE(s.done_slots, 0) * 1.5 + (COALESCE(s.done_extra_minutes,0) / 60), 2) AS done_hours
        FROM doctors d
        JOIN (
          SELECT doctor_id, COUNT(*) AS cnt
          FROM course_doctors
          GROUP BY doctor_id
        ) dc ON dc.doctor_id = d.doctor_id
        JOIN course_doctors x ON x.doctor_id = d.doctor_id
        JOIN courses c ON c.course_id = x.course_id$courseFilterSql
        LEFT JOIN (
          SELECT course_id, COUNT(*) AS cnt
          FROM course_doctors
          GROUP BY course_id
        ) cd ON cd.course_id = c.course_id
        $hJoin
        $allocJoin
        LEFT JOIN (
          $doneSubquery
        ) s ON s.doctor_id = d.doctor_id AND s.course_id = c.course_id
        ORDER BY c.year_level ASC, c.semester ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = [];
    if ($doctorScopeId > 0) $params[':doctor_id'] = $doctorScopeId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $summary = [];
    foreach ($rows as $row) {
        $year = (int)$row['year_level'];
        $sem = (int)$row['semester'];
        if ($year < 1 || $year > 3 || $sem < 1 || $sem > 2) {
            continue;
        }
        if (!isset($summary[$year])) {
            $summary[$year] = [
                1 => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
                2 => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
            ];
        }

        $allocated = (float)$row['allocated_hours'];
        $done = (float)$row['done_hours'];
        $remaining = max(0.0, round($allocated - $done, 2));

        $summary[$year][$sem]['allocated'] += $allocated;
        $summary[$year][$sem]['done'] += $done;
        $summary[$year][$sem]['remaining'] += $remaining;
    }

    $payload = [];
    for ($year = 1; $year <= 3; $year++) {
        $yearData = $summary[$year] ?? [
            1 => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
            2 => ['allocated' => 0.0, 'done' => 0.0, 'remaining' => 0.0],
        ];
        foreach ([1, 2] as $sem) {
            $yearData[$sem]['allocated'] = round($yearData[$sem]['allocated'], 2);
            $yearData[$sem]['done'] = round($yearData[$sem]['done'], 2);
            $yearData[$sem]['remaining'] = round($yearData[$sem]['remaining'], 2);
        }
        $payload[] = [
            'year_level' => $year,
            'semesters' => [
                1 => $yearData[1],
                2 => $yearData[2],
            ],
        ];
    }

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch hours summary.']);
}
