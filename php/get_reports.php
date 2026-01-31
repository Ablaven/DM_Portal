<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

$weekId = isset($_GET['week_id']) ? (int)$_GET['week_id'] : 0;
$yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0; // optional
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0; // optional

if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'year_level must be 1-3 or empty.']);
    exit;
}
if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'semester must be 1-2 or empty.']);
    exit;
}

try {
    $pdo = get_pdo();

    if ($weekId <= 0) {
        $wk = $pdo->query("SELECT week_id FROM weeks WHERE status='active' ORDER BY week_id DESC LIMIT 1")->fetch();
        if (!$wk) {
            echo json_encode(['success'=>true,'data'=>['week_id'=>null,'doctors'=>[],'courses'=>[]]]);
            exit;
        }
        $weekId = (int)$wk['week_id'];
    }

    // Doctor workload
    // - scheduled hours are still based on doctor_schedules slots.
    // - allocated hours are based on course_doctor_hours (splitting course totals among doctors).
    // If year_level/semester filters are provided, both calculations consider only courses matching the filters.
    $docStmt = $pdo->prepare(
        "SELECT d.doctor_id, d.full_name,
                -- Scheduled workload (slots in the timetable)
                SUM(CASE
                      WHEN s.schedule_id IS NULL THEN 0
                      WHEN s.counts_towards_hours <> 1 THEN 0
                      WHEN x.cancellation_id IS NOT NULL THEN 0
                      WHEN xs.slot_cancellation_id IS NOT NULL THEN 0
                      WHEN (:year_level = 0 AND :semester = 0) THEN 1
                      WHEN (:year_level <> 0 AND c.year_level <> :year_level) THEN 0
                      WHEN (:semester <> 0 AND c.semester <> :semester) THEN 0
                      ELSE 1
                    END) AS slots,
                ROUND(
                  SUM(CASE
                        WHEN s.schedule_id IS NULL THEN 0
                        WHEN s.counts_towards_hours <> 1 THEN 0
                        WHEN x.cancellation_id IS NOT NULL THEN 0
                        WHEN xs.slot_cancellation_id IS NOT NULL THEN 0
                        WHEN (:year_level = 0 AND :semester = 0) THEN 1.5
                        WHEN (:year_level <> 0 AND c.year_level <> :year_level) THEN 0
                        WHEN (:semester <> 0 AND c.semester <> :semester) THEN 0
                        ELSE 1.5
                      END)
                  +
                  SUM(CASE
                        WHEN s.schedule_id IS NULL THEN 0
                        WHEN s.counts_towards_hours <> 1 THEN 0
                        WHEN x.cancellation_id IS NOT NULL THEN 0
                        WHEN xs.slot_cancellation_id IS NOT NULL THEN 0
                        WHEN (:year_level <> 0 AND c.year_level <> :year_level) THEN 0
                        WHEN (:semester <> 0 AND c.semester <> :semester) THEN 0
                        ELSE (COALESCE(s.extra_minutes,0) / 60)
                      END)
                , 2) AS scheduled_hours,

                -- Allocated workload (planned hours split per doctor for shared courses)
                ROUND(SUM(CASE
                      WHEN h.allocated_hours IS NULL THEN 0
                      WHEN (:year_level <> 0 AND c2.year_level <> :year_level) THEN 0
                      WHEN (:semester <> 0 AND c2.semester <> :semester) THEN 0
                      ELSE h.allocated_hours
                    END), 2) AS allocated_hours
         FROM doctors d
         LEFT JOIN doctor_schedules s
           ON s.doctor_id = d.doctor_id AND s.week_id = :week_id
         LEFT JOIN courses c
           ON c.course_id = s.course_id
         LEFT JOIN doctor_week_cancellations x
           ON x.week_id = s.week_id AND x.doctor_id = s.doctor_id AND x.day_of_week = s.day_of_week
         LEFT JOIN doctor_slot_cancellations xs
           ON xs.week_id = s.week_id AND xs.doctor_id = s.doctor_id AND xs.day_of_week = s.day_of_week AND xs.slot_number = s.slot_number

         -- Allocations (independent of schedules)
         LEFT JOIN course_doctor_hours h
           ON h.doctor_id = d.doctor_id
         LEFT JOIN courses c2
           ON c2.course_id = h.course_id

         GROUP BY d.doctor_id, d.full_name
         ORDER BY d.full_name ASC"
    );
    $docStmt->execute([':week_id' => $weekId, ':year_level' => $yearLevel, ':semester' => $semester]);

    // Course summary (filterable by year_level/semester)
    $where = [];
    $params = [];
    if ($yearLevel > 0) { $where[] = 'c.year_level = :year_level'; $params[':year_level'] = $yearLevel; }
    if ($semester > 0) { $where[] = 'c.semester = :semester'; $params[':semester'] = $semester; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $courseStmt = $pdo->prepare(
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester, c.course_type, c.subject_code,
                c.total_hours,
                d.full_name AS doctor_name,
                GROUP_CONCAT(DISTINCT d2.full_name ORDER BY d2.full_name SEPARATOR ', ') AS doctor_names,
                GREATEST(0, ROUND(c.total_hours - (COALESCE(x.scheduled_base_hours,0) + COALESCE(x.scheduled_extra_hours,0)), 2)) AS remaining_hours
         FROM courses c
         LEFT JOIN doctors d ON d.doctor_id = c.doctor_id
         LEFT JOIN course_doctors cd ON cd.course_id = c.course_id
         LEFT JOIN doctors d2 ON d2.doctor_id = cd.doctor_id
         LEFT JOIN (
           SELECT s.course_id,
                  COUNT(*) AS scheduled_slots,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s
           LEFT JOIN doctor_week_cancellations cw
             ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
           LEFT JOIN doctor_slot_cancellations cs
             ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
           WHERE cw.cancellation_id IS NULL
             AND cs.slot_cancellation_id IS NULL
             AND s.counts_towards_hours = 1
           GROUP BY s.course_id
         ) x ON x.course_id = c.course_id
         $whereSql
         GROUP BY c.course_id
         ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC"
    );
    $courseStmt->execute($params);

    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => $weekId,
            'doctors' => $docStmt->fetchAll(),
            'courses' => $courseStmt->fetchAll(),
            'filters' => ['year_level' => $yearLevel, 'semester' => $semester],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to fetch reports.']);
}
