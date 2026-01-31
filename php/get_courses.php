<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

try {
    $pdo = get_pdo();

    // Remaining hours are computed: total_hours - (scheduled slots * 1.5).
    // Excludes cancelled days.
    $stmt = $pdo->query(
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                c.course_type, c.subject_code, c.total_hours,
                c.coefficient,
                c.default_room_code,
                c.doctor_id,
                d.full_name AS doctor_name,
                GROUP_CONCAT(DISTINCT cd.doctor_id ORDER BY cd.doctor_id SEPARATOR ',') AS doctor_ids,
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
         GROUP BY c.course_id
         ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC"
    );

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    // If DB schema is outdated (missing default_room_code), show a helpful message.
    $msg = 'Failed to fetch courses.';
    if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1054) {
        $msg = 'DB schema is missing default_room_code on courses. Please import the updated Digital_Marketing_Portal.sql.';
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $msg,
        // 'debug' => $e->getMessage(),
    ]);
}
