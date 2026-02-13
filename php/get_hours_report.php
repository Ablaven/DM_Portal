<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

// Admin/teacher report. Teachers are scoped to their own doctor_id.
auth_require_roles(['admin', 'teacher'], true);

$user = auth_current_user();
$role = (string)($user['role'] ?? '');
$doctorScopeId = 0;
if ($role === 'teacher') {
    $doctorScopeId = (int)($user['doctor_id'] ?? 0);
    if ($doctorScopeId <= 0) {
        echo json_encode(['success' => true, 'data' => ['doctors' => []]]);
        exit;
    }
}

try {
    $pdo = get_pdo();

    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'year_level must be 1-3 or empty.']);
        exit;
    }
    if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'semester must be 1-2 or empty.']);
        exit;
    }
    // Ensure optional tables exist / handle older DBs gracefully.
    // IMPORTANT: do NOT use $pdo->exec() with SELECT statements.
    // Some MySQL/PDO configurations use unbuffered queries and can throw:
    //   SQLSTATE[HY000]: General error: 2014 Cannot execute queries while other unbuffered queries are active
    // if a result set isn't fully consumed.
    $touchTable = function (string $table) use ($pdo): bool {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($stmt) $stmt->fetch(PDO::FETCH_NUM);
            if ($stmt) $stmt->closeCursor();
            return true;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1146) return false; // table doesn't exist
            throw $e;
        }
    };

    // Required: course_doctors
    if (!$touchTable('course_doctors')) {
        echo json_encode(['success' => true, 'data' => ['doctors' => []]]);
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

    // If schedules table doesn't exist, still include the columns so the outer query doesn't break.
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

    // done_hours is computed from scheduled slots (counts_towards_hours=1) excluding cancellations.
    // allocated_hours is taken from course_doctor_hours when present.
    // If allocated_hours is missing and the course has only one assigned doctor, fall back to courses.total_hours.
    $where = [];
    if ($yearLevel > 0) { $where[] = 'c.year_level = :year_level'; }
    if ($semester > 0) { $where[] = 'c.semester = :semester'; }
    if ($doctorScopeId > 0) { $where[] = 'd.doctor_id = :doctor_id'; }
    // Important: inject filters into the JOIN condition (not as a WHERE in the middle of JOIN clauses)
    // so the SQL remains valid regardless of additional joins.
    $courseFilterSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

    $allocJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

    $sql = "
        SELECT
          d.doctor_id,
          d.full_name,
          c.course_id,
          c.course_name,
          c.course_type,
          c.subject_code,
          c.total_hours,
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
        ORDER BY d.full_name ASC, c.program ASC, c.year_level ASC, c.course_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = [];
    if ($yearLevel > 0) $params[':year_level'] = $yearLevel;
    if ($semester > 0) $params[':semester'] = $semester;
    if ($doctorScopeId > 0) $params[':doctor_id'] = $doctorScopeId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Shape results: group by doctor.
    $byDoctor = [];
    foreach ($rows as $r) {
        $docId = (int)$r['doctor_id'];
        if (!isset($byDoctor[$docId])) {
            $byDoctor[$docId] = [
                'doctor_id' => $docId,
                'full_name' => $r['full_name'],
                'courses' => [],
                'totals' => [
                    'allocated_hours' => 0.0,
                    'done_hours' => 0.0,
                    'remaining_hours' => 0.0,
                ],
            ];
        }

        $allocated = (float)$r['allocated_hours'];
        $done = (float)$r['done_hours'];
        $remaining = max(0.0, round($allocated - $done, 2));

        $byDoctor[$docId]['courses'][] = [
            'course_id' => (int)$r['course_id'],
            'course_name' => $r['course_name'],
            'course_type' => $r['course_type'],
            'subject_code' => $r['subject_code'],
            'allocated_hours' => round($allocated, 2),
            'done_hours' => round($done, 2),
            'remaining_hours' => $remaining,
        ];

        $byDoctor[$docId]['totals']['allocated_hours'] += $allocated;
        $byDoctor[$docId]['totals']['done_hours'] += $done;
        $byDoctor[$docId]['totals']['remaining_hours'] += $remaining;
    }

    // Round totals.
    $doctors = array_values($byDoctor);
    foreach ($doctors as &$d) {
        $d['totals']['allocated_hours'] = round((float)$d['totals']['allocated_hours'], 2);
        $d['totals']['done_hours'] = round((float)$d['totals']['done_hours'], 2);
        $d['totals']['remaining_hours'] = round((float)$d['totals']['remaining_hours'], 2);
    }
    unset($d);

    echo json_encode(['success' => true, 'data' => ['doctors' => $doctors]]);
} catch (Throwable $e) {
    http_response_code(500);
    $debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

    $debugInfo = null;
    if ($debug) {
        $debugInfo = [
            'message' => $e->getMessage(),
            'type' => get_class($e),
        ];
        if ($e instanceof PDOException) {
            $debugInfo['sqlstate'] = $e->getCode();
            $debugInfo['errorInfo'] = $e->errorInfo ?? null;
        }
    }

    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch hours report.',
        'debug' => $debugInfo,
    ]);
}
