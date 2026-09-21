<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_session_helpers.php';
require_once __DIR__ . '/_slot_time_helpers.php';

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_attendance_sessions_table($pdo);

    $weekIdFrom = isset($_GET['week_id_from']) ? (int)$_GET['week_id_from'] : 0;
    $weekIdTo   = isset($_GET['week_id_to'])   ? (int)$_GET['week_id_to']   : 0;

    if ($weekIdFrom <= 0 || $weekIdTo <= 0) {
        bad_request('week_id_from and week_id_to are required.');
    }

    if ($weekIdFrom > $weekIdTo) {
        bad_request('week_id_from must be less than or equal to week_id_to.');
    }

    // Get all scheduled slots in this week range with their attendance status
    $sql = "
        SELECT
            s.schedule_id,
            s.week_id,
            s.day_of_week,
            s.slot_number,
            s.course_id,
            s.doctor_id,
            w.label AS week_label,
            w.start_date,
            w.is_ramadan,
            w.term_id,
            c.course_name,
            c.subject_code,
            c.course_type,
            c.program,
            c.year_level,
            c.semester,
            d.full_name AS doctor_name,
            d.email AS doctor_email,
            ash.opened_at,
            ash.hours_counted,
            CASE
                WHEN cw.cancellation_id IS NOT NULL THEN 1
                WHEN cs.slot_cancellation_id IS NOT NULL THEN 1
                ELSE 0
            END AS is_canceled
        FROM doctor_schedules s
        JOIN weeks w ON w.week_id = s.week_id
        JOIN courses c ON c.course_id = s.course_id
        JOIN doctors d ON d.doctor_id = s.doctor_id
        LEFT JOIN attendance_sessions ash
            ON ash.term_id = w.term_id AND ash.schedule_id = s.schedule_id
        LEFT JOIN doctor_week_cancellations cw
            ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
        LEFT JOIN doctor_slot_cancellations cs
            ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id
                AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
        WHERE s.week_id >= :week_id_from
          AND s.week_id <= :week_id_to
          AND s.counts_towards_hours = 1
        ORDER BY s.week_id ASC, s.day_of_week ASC, s.slot_number ASC, c.course_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':week_id_from' => $weekIdFrom,
        ':week_id_to'   => $weekIdTo,
    ]);

    $rows = $stmt->fetchAll();

    // Process each row to add computed fields
    $results = [];
    foreach ($rows as $row) {
        $isCanceled = (int)$row['is_canceled'] === 1;
        $attendanceTaken = $row['opened_at'] !== null;
        $hoursCounted = (int)($row['hours_counted'] ?? 0) === 1;

        // Calculate the actual lecture date/time
        $weekStart = $row['start_date'];
        $dayOfWeek = $row['day_of_week'];
        $slotNumber = (int)$row['slot_number'];
        $isRamadan = (int)$row['is_ramadan'] === 1;

        $lectureRange = dmportal_schedule_lecture_range_from_parts(
            $weekStart,
            $isRamadan,
            $dayOfWeek,
            $slotNumber
        );

        $lectureDate = '';
        $lectureTime = '';
        if ($lectureRange) {
            $lectureDate = $lectureRange['start']->format('Y-m-d');
            $lectureTime = $lectureRange['start']->format('H:i') . ' - ' . $lectureRange['end']->format('H:i');
        }

        $results[] = [
            'schedule_id'       => (int)$row['schedule_id'],
            'week_id'           => (int)$row['week_id'],
            'week_label'        => $row['week_label'],
            'day_of_week'       => $row['day_of_week'],
            'slot_number'       => $slotNumber,
            'lecture_date'      => $lectureDate,
            'lecture_time'      => $lectureTime,
            'course_id'         => (int)$row['course_id'],
            'course_name'       => $row['course_name'],
            'subject_code'      => $row['subject_code'],
            'course_type'       => $row['course_type'],
            'program'           => $row['program'],
            'year_level'        => (int)$row['year_level'],
            'semester'          => (int)$row['semester'],
            'doctor_id'         => (int)$row['doctor_id'],
            'doctor_name'       => $row['doctor_name'],
            'doctor_email'      => $row['doctor_email'],
            'is_canceled'       => $isCanceled,
            'attendance_taken'  => $attendanceTaken,
            'hours_counted'     => $hoursCounted,
            'opened_at'         => $row['opened_at'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch attendance tracking report.',
        // 'debug' => $e->getMessage(),
    ]);
}
