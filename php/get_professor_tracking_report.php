<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_session_helpers.php';
require_once __DIR__ . '/_slot_time_helpers.php';

auth_require_roles(['admin', 'management'], true);

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    dmportal_ensure_attendance_sessions_table($pdo);

    $weekIdFrom = isset($_GET['week_id_from']) ? (int)$_GET['week_id_from'] : 0;
    $weekIdTo   = isset($_GET['week_id_to'])   ? (int)$_GET['week_id_to']   : 0;

    if ($weekIdFrom <= 0 || $weekIdTo <= 0) {
        echo json_encode(['success' => false, 'error' => 'week_id_from and week_id_to are required.']);
        exit;
    }

    if ($weekIdFrom > $weekIdTo) {
        echo json_encode(['success' => false, 'error' => 'week_id_from must be less than or equal to week_id_to.']);
        exit;
    }

    // Get all scheduled slots in this week range with their attendance status
    // Professor is PRESENT if they took attendance (opened_at IS NOT NULL)
    // Professor is ABSENT if they did NOT take attendance (opened_at IS NULL)
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

    // Build response data
    $lectures = [];
    $totalLectures = 0;
    $professorPresent = 0;
    $professorAbsent = 0;
    $canceled = 0;

    foreach ($rows as $row) {
        $isCanceled = (int)$row['is_canceled'] === 1;
        $attendanceTaken = $row['opened_at'] !== null;

        // Calculate lecture date/time
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
            $lectureDate = $lectureRange['start']->format('M j, Y');
            $lectureTime = $lectureRange['start']->format('g:i A') . ' - ' . $lectureRange['end']->format('g:i A');
        }

        // Determine status
        $status = 'Scheduled';
        $teacherAttendance = 'No'; // Default: Absent
        
        if ($isCanceled) {
            $status = 'Canceled';
            $teacherAttendance = 'Canceled';
            $canceled++;
        } elseif ($attendanceTaken) {
            $teacherAttendance = 'Yes'; // Present
            $professorPresent++;
        } else {
            $professorAbsent++;
        }

        $totalLectures++;

        $lectures[] = [
            'schedule_id' => $row['schedule_id'],
            'week_id' => $row['week_id'],
            'week_label' => $row['week_label'],
            'date' => $lectureDate,
            'day' => $dayOfWeek,
            'time' => $lectureTime,
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'subject_code' => $row['subject_code'] ?: '-',
            'course_type' => $row['course_type'],
            'program' => $row['program'],
            'year_level' => $row['year_level'],
            'semester' => $row['semester'],
            'doctor_id' => $row['doctor_id'],
            'doctor_name' => $row['doctor_name'],
            'doctor_email' => $row['doctor_email'],
            'status' => $status,
            'teacher_attendance' => $teacherAttendance,
            'is_canceled' => $isCanceled,
            'attendance_taken' => $attendanceTaken,
        ];
    }

    // Calculate attendance rate (excluding canceled)
    $scheduledLectures = $totalLectures - $canceled;
    $attendanceRate = $scheduledLectures > 0 ? round(($professorPresent / $scheduledLectures) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'lectures' => $lectures,
            'summary' => [
                'total_lectures' => $totalLectures,
                'professor_present' => $professorPresent,
                'professor_absent' => $professorAbsent,
                'canceled' => $canceled,
                'attendance_rate' => $attendanceRate,
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Professor tracking report error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
