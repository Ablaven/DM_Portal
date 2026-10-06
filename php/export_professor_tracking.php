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

// Slot time definitions
const SLOT_TIMES = [
    1 => ['start' => '8:30 AM', 'end' => '10:00 AM'],
    2 => ['start' => '10:10 AM', 'end' => '11:30 AM'],
    3 => ['start' => '11:40 AM', 'end' => '1:00 PM'],
    4 => ['start' => '1:10 PM', 'end' => '2:40 PM'],
    5 => ['start' => '2:50 PM', 'end' => '4:20 PM'],
];

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];

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
            c.year_level,
            d.full_name AS doctor_name,
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
        ORDER BY s.week_id ASC, c.year_level ASC, s.day_of_week ASC, s.slot_number ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':week_id_from' => $weekIdFrom,
        ':week_id_to'   => $weekIdTo,
    ]);

    $rows = $stmt->fetchAll();

    // Organize data: [year][week_id][day][slot]
    $dataByYear = [];
    $weekLabels = [];
    
    foreach ($rows as $row) {
        $year = (int)$row['year_level'];
        $weekId = (int)$row['week_id'];
        $day = $row['day_of_week'];
        $slot = (int)$row['slot_number'];
        
        if (!isset($dataByYear[$year])) {
            $dataByYear[$year] = [];
        }
        if (!isset($dataByYear[$year][$weekId])) {
            $dataByYear[$year][$weekId] = [];
        }
        if (!isset($dataByYear[$year][$weekId][$day])) {
            $dataByYear[$year][$weekId][$day] = [];
        }
        
        $isCanceled = (int)$row['is_canceled'] === 1;
        $attendanceTaken = $row['opened_at'] !== null;
        
        $status = 'present'; // Default
        if ($isCanceled) {
            $status = 'canceled';
        } elseif (!$attendanceTaken) {
            $status = 'absent';
        }
        
        $dataByYear[$year][$weekId][$day][$slot] = [
            'status' => $status,
            'doctor_name' => $row['doctor_name'],
            'course_name' => $row['course_name'],
            'subject_code' => $row['subject_code'],
        ];
        
        // Store week label
        if (!isset($weekLabels[$weekId])) {
            $weekLabels[$weekId] = $row['week_label'];
        }
    }

    // Create XLSX with multiple sheets
    $xlsx = new SimpleXlsxWriter();
    
    // Sort years
    ksort($dataByYear);
    
    // Create a sheet for each year
    foreach ([1, 2, 3] as $year) {
        $yearData = $dataByYear[$year] ?? [];
        
        // Build rows for this year
        $sheetRows = [];
        $styleMap = [];
        $rowHeights = [];
        $merges = [];
        
        $currentRow = 0;
        
        // Get week IDs for this year and sort them
        $weekIds = array_keys($yearData);
        sort($weekIds);
        
        foreach ($weekIds as $weekId) {
            $weekData = $yearData[$weekId];
            $weekLabel = $weekLabels[$weekId] ?? "Week $weekId";
            
            // Add week header row (merged across all columns)
            $sheetRows[] = [$weekLabel, '', '', '', '', ''];
            $styleMap[$currentRow] = [
                0 => $xlsx->styleTitle(), 
                1 => $xlsx->styleTitle(), 
                2 => $xlsx->styleTitle(), 
                3 => $xlsx->styleTitle(), 
                4 => $xlsx->styleTitle(), 
                5 => $xlsx->styleTitle()
            ];
            $rowHeights[$currentRow] = 30;
            $merges[] = "A" . ($currentRow + 1) . ":F" . ($currentRow + 1); // Excel is 1-indexed
            $currentRow++;
            
            // Add table header
            $sheetRows[] = ['Time', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
            $styleMap[$currentRow] = [
                0 => $xlsx->styleHeader(),
                1 => $xlsx->styleHeader(),
                2 => $xlsx->styleHeader(),
                3 => $xlsx->styleHeader(),
                4 => $xlsx->styleHeader(),
                5 => $xlsx->styleHeader()
            ];
            $rowHeights[$currentRow] = 25;
            $currentRow++;
            
            // Add rows for each slot
            foreach ([1, 2, 3, 4, 5] as $slot) {
                $timeRange = SLOT_TIMES[$slot]['start'] . ' - ' . SLOT_TIMES[$slot]['end'];
                $row = [$timeRange];
                $styleMap[$currentRow] = [0 => $xlsx->styleSlot()];
                
                foreach (DAYS as $idx => $day) {
                    $lecture = $weekData[$day][$slot] ?? null;
                    
                    if (!$lecture) {
                        // No lecture scheduled - gray background
                        $row[] = '';
                        $styleMap[$currentRow][$idx + 1] = $xlsx->styleFill('F3F4F6'); // light gray
                    } elseif ($lecture['status'] === 'present') {
                        // Professor was present - green background
                        $row[] = '✓';
                        $styleMap[$currentRow][$idx + 1] = $xlsx->styleFill('D1FAE5'); // green
                    } elseif ($lecture['status'] === 'canceled') {
                        // Lecture was canceled - gray background
                        $row[] = 'Canceled';
                        $styleMap[$currentRow][$idx + 1] = $xlsx->styleFill('E5E7EB'); // gray
                    } else {
                        // Professor was absent - red background, show details on 3 lines
                        $cellContent = $lecture['doctor_name'] . "\n" . 
                                     $lecture['course_name'] . "\n" . 
                                     $lecture['subject_code'];
                        $row[] = $cellContent;
                        $styleMap[$currentRow][$idx + 1] = $xlsx->styleFill('FEE2E2'); // red
                    }
                }
                
                $sheetRows[] = $row;
                $rowHeights[$currentRow] = 80; // Taller rows for multi-line content (3 lines of text)
                $currentRow++;
            }
            
            // Add empty row between weeks
            $sheetRows[] = ['', '', '', '', '', ''];
            $styleMap[$currentRow] = [
                0 => $xlsx->styleCell(),
                1 => $xlsx->styleCell(),
                2 => $xlsx->styleCell(),
                3 => $xlsx->styleCell(),
                4 => $xlsx->styleCell(),
                5 => $xlsx->styleCell()
            ];
            $rowHeights[$currentRow] = 10;
            $currentRow++;
        }
        
        // If no data for this year, add a message
        if (empty($sheetRows)) {
            $sheetRows[] = ['No lectures scheduled for Year ' . $year];
            $styleMap[0] = [0 => $xlsx->styleCell()];
        }
        
        // Add sheet with freeze panes (freeze first column = time, freeze top 2 rows for each week)
        $xlsx->addSheet("Year $year", $sheetRows, [
            'colWidths' => [
                0 => 22,  // Time column
                1 => 35,  // Sunday (wider for professor names)
                2 => 35,  // Monday
                3 => 35,  // Tuesday
                4 => 35,  // Wednesday
                5 => 35,  // Thursday
            ],
            'rowHeights' => $rowHeights,
            'styleMap' => $styleMap,
            'merges' => $merges,
            'freezeTopRows' => 2, // Freeze week header + table header
        ]);
    }

    // Output file
    $filename = 'professor_attendance_timetable_' . date('Y-m-d_His') . '.xlsx';

    $xlsx->download($filename);
    exit;
} catch (Throwable $e) {
    // Log error for debugging
    error_log('Export professor tracking error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to generate Excel file: ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
