<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_roles(['admin']);

// Get parameters
$weekIdFrom = isset($_GET['week_id_from']) ? (int)$_GET['week_id_from'] : 0;
$weekIdTo = isset($_GET['week_id_to']) ? (int)$_GET['week_id_to'] : 0;
$nationalityFilter = isset($_GET['nationality']) ? trim($_GET['nationality']) : 'All';

if ($weekIdFrom <= 0 || $weekIdTo <= 0) {
    http_response_code(400);
    die('Invalid week range.');
}

if ($weekIdFrom > $weekIdTo) {
    http_response_code(400);
    die('From week must be <= to week.');
}

// Validate nationality filter
if (!in_array($nationalityFilter, ['French', 'Egyptian', 'All'], true)) {
    $nationalityFilter = 'All';
}

try {
    $pdo = get_pdo();

    // Get teachers based on nationality filter
    $doctorQuery = "SELECT doctor_id, full_name, doctor_type FROM doctors WHERE 1=1";
    $doctorParams = [];
    
    if ($nationalityFilter === 'French') {
        $doctorQuery .= " AND doctor_type = :doctor_type";
        $doctorParams[':doctor_type'] = 'French';
    } elseif ($nationalityFilter === 'Egyptian') {
        $doctorQuery .= " AND doctor_type = :doctor_type";
        $doctorParams[':doctor_type'] = 'Egyptian';
    }
    
    $doctorQuery .= " ORDER BY full_name";
    
    $stmt = $pdo->prepare($doctorQuery);
    $stmt->execute($doctorParams);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($teachers)) {
        http_response_code(404);
        die('No teachers found for the selected filter.');
    }

    // Get all weeks in range with their dates
    $weeksStmt = $pdo->prepare("
        SELECT week_id, label, start_date, end_date, term_id
        FROM weeks
        WHERE week_id >= :from AND week_id <= :to
        ORDER BY week_id
    ");
    $weeksStmt->execute([':from' => $weekIdFrom, ':to' => $weekIdTo]);
    $weeks = $weeksStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($weeks)) {
        http_response_code(404);
        die('No weeks found in the specified range.');
    }
    
    // Create a lookup map for weeks by week_id
    $weeksById = [];
    foreach ($weeks as $week) {
        $weeksById[$week['week_id']] = $week;
    }

    // Get schedules for all teachers and weeks
    $scheduleStmt = $pdo->prepare("
        SELECT 
            ds.schedule_id,
            ds.week_id,
            ds.doctor_id,
            ds.day_of_week,
            ds.slot_number,
            ds.room_code,
            c.course_name,
            w.start_date,
            w.label as week_label
        FROM doctor_schedules ds
        INNER JOIN courses c ON ds.course_id = c.course_id
        INNER JOIN weeks w ON ds.week_id = w.week_id
        WHERE ds.week_id >= :from AND ds.week_id <= :to
        ORDER BY ds.week_id, ds.day_of_week, ds.slot_number
    ");
    $scheduleStmt->execute([':from' => $weekIdFrom, ':to' => $weekIdTo]);
    $allSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all cancellations in range
    $cancelStmt = $pdo->prepare("
        SELECT week_id, doctor_id, day_of_week, slot_number
        FROM doctor_slot_cancellations
        WHERE week_id >= :from AND week_id <= :to
    ");
    $cancelStmt->execute([':from' => $weekIdFrom, ':to' => $weekIdTo]);
    $cancellations = $cancelStmt->fetchAll(PDO::FETCH_ASSOC);

    // Index cancellations for quick lookup
    $cancelIndex = [];
    foreach ($cancellations as $cancel) {
        $key = $cancel['week_id'] . '_' . $cancel['doctor_id'] . '_' . $cancel['day_of_week'] . '_' . $cancel['slot_number'];
        $cancelIndex[$key] = true;
    }

    // Organize schedules by teacher and week
    $teacherSchedules = [];
    foreach ($teachers as $teacher) {
        $teacherSchedules[$teacher['doctor_id']] = [
            'name' => $teacher['full_name'],
            'nationality' => $teacher['doctor_type'],
            'weeks' => []
        ];
    }

    foreach ($allSchedules as $schedule) {
        $doctorId = $schedule['doctor_id'];
        $weekId = $schedule['week_id'];
        
        if (isset($teacherSchedules[$doctorId])) {
            if (!isset($teacherSchedules[$doctorId]['weeks'][$weekId])) {
                $teacherSchedules[$doctorId]['weeks'][$weekId] = [];
            }
            
            // Check if this slot is cancelled
            $cancelKey = $weekId . '_' . $doctorId . '_' . $schedule['day_of_week'] . '_' . $schedule['slot_number'];
            $isCancelled = isset($cancelIndex[$cancelKey]);
            
            $teacherSchedules[$doctorId]['weeks'][$weekId][] = [
                'day' => $schedule['day_of_week'],
                'slot' => $schedule['slot_number'],
                'course' => $schedule['course_name'],
                'room' => $schedule['room_code'],
                'week_label' => $schedule['week_label'],
                'is_cancelled' => $isCancelled
            ];
        }
    }

    // Create XLSX writer
    $xlsx = new SimpleXlsxWriter();
    $sheetsAdded = 0;

    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
    $slots = [
        1 => '8:30 AM - 10:15 AM',
        2 => '10:10 AM - 11:45 AM',
        3 => '11:40 AM - 1:15 PM',
        4 => '1:10 PM - 2:55 PM',
        5 => '2:50 PM - 4:35 PM'
    ];

    // For each teacher, create a sheet
    foreach ($teacherSchedules as $doctorId => $teacherData) {
        $rows = [];
        $styleMap = [];
        $rowHeights = [];
        $merges = [];
        $currentRow = 0;

        // Row 1: Teacher name title (merged across all columns)
        $rows[] = [$teacherData['name'], '', '', '', '', ''];
        $styleMap[$currentRow] = [0 => $xlsx->styleTitle()];
        $merges[] = 'A' . ($currentRow + 1) . ':F' . ($currentRow + 1);
        $rowHeights[$currentRow] = 24;
        $currentRow++;

        // Row 2: Nationality info
        $nationalityLabel = $teacherData['nationality'] === 'French' ? 'French Teacher' : 
                           ($teacherData['nationality'] === 'Egyptian' ? 'Egyptian Teacher' : $teacherData['nationality']);
        $rows[] = [$nationalityLabel, '', '', '', '', ''];
        $styleMap[$currentRow] = [0 => $xlsx->styleCellSmallBold()];
        $merges[] = 'A' . ($currentRow + 1) . ':F' . ($currentRow + 1);
        $rowHeights[$currentRow] = 18;
        $currentRow++;

        // Empty row
        $rows[] = ['', '', '', '', '', ''];
        $styleMap[$currentRow] = [];
        $rowHeights[$currentRow] = 12;
        $currentRow++;

        // Get only the weeks where this teacher has schedules
        $teacherWeekIds = array_keys($teacherData['weeks']);
        
        // If teacher has no schedules at all, skip this teacher
        if (empty($teacherWeekIds)) {
            continue;
        }
        
        // For each week where teacher has schedules, create a schedule grid
        foreach ($teacherWeekIds as $weekId) {
            // Skip if week data not found
            if (!isset($weeksById[$weekId])) {
                continue;
            }
            
            $week = $weeksById[$weekId];
            $weekLabel = $week['label'] ?: 'Week ' . $weekId;
            
            // Add date range to week label if available
            if (!empty($week['start_date'])) {
                try {
                    $startDate = new DateTime($week['start_date']);
                    
                    if (!empty($week['end_date'])) {
                        // Both start and end dates available
                        $endDate = new DateTime($week['end_date']);
                        $dateRange = $startDate->format('d/m/Y') . ' ~ ' . $endDate->format('d/m/Y');
                    } else {
                        // Only start date available
                        $dateRange = 'Starting ' . $startDate->format('d/m/Y');
                    }
                    
                    $weekLabel .= ' (' . $dateRange . ')';
                } catch (Exception $e) {
                    // If date parsing fails, just use the week label
                }
            }
            
            // Week header (merged)
            $rows[] = [$weekLabel, '', '', '', '', ''];
            $styleMap[$currentRow] = [0 => $xlsx->styleTitle()];
            $merges[] = 'A' . ($currentRow + 1) . ':F' . ($currentRow + 1);
            $rowHeights[$currentRow] = 22;
            $currentRow++;

            // Table header row (Time, Sun, Mon, Tue, Wed, Thu)
            $headerRow = ['Time'];
            foreach ($days as $day) {
                $headerRow[] = $day;
            }
            $rows[] = $headerRow;
            $headerStyles = [];
            for ($i = 0; $i < 6; $i++) {
                $headerStyles[$i] = $xlsx->styleHeader();
            }
            $styleMap[$currentRow] = $headerStyles;
            $rowHeights[$currentRow] = 20;
            $currentRow++;

            // Get schedules for this week and teacher
            $weekSchedules = $teacherData['weeks'][$weekId] ?? [];
            
            // Organize by slot and day
            $grid = [];
            foreach ($weekSchedules as $sched) {
                $slot = $sched['slot'];
                $day = $sched['day'];
                if (!isset($grid[$slot])) {
                    $grid[$slot] = [];
                }
                $grid[$slot][$day] = $sched;
            }

            // Create rows for each time slot
            foreach ($slots as $slotNum => $slotTime) {
                $row = [$slotTime]; // First column is time
                $rowStyles = [0 => $xlsx->styleSlot()];
                
                $isStripeRow = ($slotNum % 2 === 0);
                
                foreach ($days as $day) {
                    $cellContent = '';
                    $styleId = null;
                    
                    if (isset($grid[$slotNum][$day])) {
                        $sched = $grid[$slotNum][$day];
                        
                        if ($sched['is_cancelled']) {
                            // Cancelled slot
                            $cellContent = "CANCELLED\n" . $sched['course'];
                            if ($sched['room']) {
                                $cellContent .= "\nRoom: " . $sched['room'];
                            }
                            $styleId = $xlsx->styleCancelled();
                        } else {
                            // Normal scheduled lecture
                            $cellContent = $sched['course'];
                            if ($sched['room']) {
                                $cellContent .= "\nRoom: " . $sched['room'];
                            }
                            // Use light blue fill for scheduled lectures
                            $styleId = $xlsx->styleLectureFill('E3F2FD');
                        }
                    } else {
                        // Empty slot - use stripe or normal
                        $cellContent = '';
                        $styleId = $isStripeRow ? $xlsx->styleStripe() : $xlsx->styleCell();
                    }
                    
                    $row[] = $cellContent;
                    $rowStyles[] = $styleId;
                }
                
                $rows[] = $row;
                $styleMap[$currentRow] = $rowStyles;
                $rowHeights[$currentRow] = 60;
                $currentRow++;
            }

            // Add spacing between weeks (2 empty rows)
            $rows[] = ['', '', '', '', '', ''];
            $styleMap[$currentRow] = [];
            $rowHeights[$currentRow] = 12;
            $currentRow++;
            
            $rows[] = ['', '', '', '', '', ''];
            $styleMap[$currentRow] = [];
            $rowHeights[$currentRow] = 12;
            $currentRow++;
        }

        // Add this teacher's sheet
        $sheetName = substr($teacherData['name'], 0, 31); // Excel limit
        $xlsx->addSheet($sheetName, $rows, [
            'styleMap' => $styleMap,
            'colWidths' => [
                0 => 28, // Time column (wider)
                1 => 40, // Sun
                2 => 40, // Mon
                3 => 40, // Tue
                4 => 40, // Wed
                5 => 40  // Thu
            ],
            'rowHeights' => $rowHeights,
            'merges' => $merges
        ]);
        
        $sheetsAdded++;
    }

    // Check if we have any sheets to export
    if ($sheetsAdded === 0) {
        http_response_code(404);
        die('No teacher schedules found in the specified week range.');
    }

    // Generate filename
    $filename = 'Teachers_Schedule_Week' . $weekIdFrom . '-' . $weekIdTo . '.xlsx';

    // Output Excel file
    $xlsx->download($filename);

} catch (Exception $e) {
    error_log("Teacher Schedule Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Failed to generate Excel file: ' . $e->getMessage());
}
