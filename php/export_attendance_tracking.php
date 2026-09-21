<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_session_helpers.php';
require_once __DIR__ . '/_slot_time_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    die($m);
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

    // Build data rows for export
    $exportRows = [];
    
    // Header row
    $exportRows[] = [
        'Week',
        'Date',
        'Day',
        'Time',
        'Course Name',
        'Subject Code',
        'Type',
        'Program',
        'Year',
        'Sem',
        'Professor',
        'Email',
        'Status',
        'Attendance',
        'Hours',
    ];

    // Style map: row => col => styleId
    $styleMap = [];
    $styleMap[0] = []; // header row - all cells get header style
    for ($col = 0; $col < 15; $col++) {
        $styleMap[0][$col] = 1; // header style
    }

    // We need custom fill styles for red/green cells
    $xlsx = new SimpleXlsxWriter();

    // Data rows
    $rowNum = 1;
    foreach ($rows as $row) {
        $isCanceled = (int)$row['is_canceled'] === 1;
        $attendanceTaken = $row['opened_at'] !== null;
        $hoursCounted = (int)($row['hours_counted'] ?? 0) === 1;

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
        if ($isCanceled) {
            $status = 'Canceled';
        }

        // Format course info better
        $courseInfo = $row['course_name'];
        $subjectCode = $row['subject_code'] ? $row['subject_code'] : '—';
        $programInfo = $row['program'] . ' Y' . $row['year_level'] . ' S' . $row['semester'];

        // Data
        $exportRows[] = [
            $row['week_label'],
            $lectureDate,
            $dayOfWeek,
            $lectureTime,
            $courseInfo,
            $subjectCode,
            $row['course_type'],
            $row['program'],
            $row['year_level'],
            $row['semester'],
            $row['doctor_name'],
            $row['doctor_email'],
            $status,
            $attendanceTaken ? 'Yes' : 'No',
            $hoursCounted ? 'Yes' : 'No',
        ];

        // Apply row styling
        $styleMap[$rowNum] = [];
        for ($col = 0; $col < 15; $col++) {
            // Determine style for each cell
            if ($col === 13) {
                // Attendance Taken column
                if ($isCanceled) {
                    $styleMap[$rowNum][$col] = 3; // normal
                } elseif ($attendanceTaken) {
                    $styleMap[$rowNum][$col] = $xlsx->styleFill('D1FAE5'); // green
                } else {
                    $styleMap[$rowNum][$col] = $xlsx->styleFill('FEE2E2'); // red
                }
            } elseif ($col === 14) {
                // Hours Counted column
                if ($isCanceled) {
                    $styleMap[$rowNum][$col] = 3; // normal
                } elseif ($hoursCounted) {
                    $styleMap[$rowNum][$col] = $xlsx->styleFill('D1FAE5'); // green
                } else {
                    $styleMap[$rowNum][$col] = $xlsx->styleFill('FEE2E2'); // red
                }
            } else {
                $styleMap[$rowNum][$col] = 3; // normal cell style
            }
        }

        $rowNum++;
    }

    // Create XLSX
    $xlsx->addSheet('Attendance Tracking', $exportRows, [
        'styleMap' => $styleMap,
        'colWidths' => [
            0 => 15,   // Week
            1 => 16,   // Date
            2 => 10,   // Day
            3 => 18,   // Time
            4 => 35,   // Course Name
            5 => 15,   // Subject Code
            6 => 12,   // Type
            7 => 25,   // Program
            8 => 8,    // Year
            9 => 8,    // Sem
            10 => 25,  // Professor
            11 => 30,  // Email
            12 => 14,  // Status
            13 => 14,  // Attendance Taken
            14 => 14,  // Hours Counted
        ],
        'rowHeights' => [
            0 => 25, // header row height
        ],
    ]);

    // Output file
    $filename = 'attendance_tracking_' . date('Y-m-d_His') . '.xlsx';

    $xlsx->download($filename);
    exit;
} catch (Throwable $e) {
    // Log error for debugging
    error_log('Export attendance tracking error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to generate Excel file: ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
