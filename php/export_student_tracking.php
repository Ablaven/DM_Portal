<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    die($m);
}

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];

try {
    $pdo = get_pdo();

    $yearLevel = isset($_GET['year_level']) && $_GET['year_level'] !== '' ? (int)$_GET['year_level'] : null;
    $studentId = isset($_GET['student_id']) && $_GET['student_id'] !== 'all' && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;
    $courseId = isset($_GET['course_id']) && $_GET['course_id'] !== 'all' && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : null;
    $termId = isset($_GET['term_id']) && $_GET['term_id'] !== '' ? (int)$_GET['term_id'] : null;
    $weekIdFrom = isset($_GET['week_id_from']) && $_GET['week_id_from'] !== '' ? (int)$_GET['week_id_from'] : null;
    $weekIdTo = isset($_GET['week_id_to']) && $_GET['week_id_to'] !== '' ? (int)$_GET['week_id_to'] : null;

    if ($yearLevel === null) {
        bad_request('year_level is required');
    }

    // Build student list
    $students = [];
    if ($studentId !== null) {
        // Single student
        $stmt = $pdo->prepare('SELECT student_id, full_name, student_code FROM students WHERE student_id = :id AND year_level = :year LIMIT 1');
        $stmt->execute([':id' => $studentId, ':year' => $yearLevel]);
        $student = $stmt->fetch();
        if ($student) {
            $students[] = $student;
        }
    } else {
        // All students in year
        $stmt = $pdo->prepare('SELECT student_id, full_name, student_code FROM students WHERE year_level = :year ORDER BY full_name ASC');
        $stmt->execute([':year' => $yearLevel]);
        $students = $stmt->fetchAll();
    }

    if (empty($students)) {
        bad_request('No students found');
    }

    // Build WHERE clauses for filtering
    $whereConditions = ['s.week_id IS NOT NULL'];
    $params = [];

    if ($termId !== null) {
        $whereConditions[] = 'w.term_id = :term_id';
        $params[':term_id'] = $termId;
    }

    if ($weekIdFrom !== null) {
        $whereConditions[] = 's.week_id >= :week_id_from';
        $params[':week_id_from'] = $weekIdFrom;
    }

    if ($weekIdTo !== null) {
        $whereConditions[] = 's.week_id <= :week_id_to';
        $params[':week_id_to'] = $weekIdTo;
    }

    if ($courseId !== null) {
        $whereConditions[] = 's.course_id = :course_id';
        $params[':course_id'] = $courseId;
    }

    $whereConditions[] = 'c.year_level = :year_level';
    $params[':year_level'] = $yearLevel;

    $whereClause = implode(' AND ', $whereConditions);

    // Create XLSX
    $xlsx = new SimpleXlsxWriter();

    // For each student, create a sheet
    foreach ($students as $student) {
        $studentIdVal = (int)$student['student_id'];
        $studentName = (string)($student['full_name'] ?? 'Student');
        $studentCode = (string)($student['student_code'] ?? '');

        // Get all attendance records for this student
        $sql = "
            SELECT
                ar.schedule_id,
                ar.status,
                s.week_id,
                s.day_of_week,
                s.slot_number,
                s.course_id,
                w.label AS week_label,
                w.start_date,
                c.course_name,
                c.subject_code
            FROM attendance_records ar
            JOIN doctor_schedules s ON s.schedule_id = ar.schedule_id
            JOIN weeks w ON w.week_id = s.week_id
            JOIN courses c ON c.course_id = s.course_id
            WHERE ar.student_id = :student_id
              AND {$whereClause}
            ORDER BY c.course_name ASC, s.week_id ASC, s.day_of_week ASC, s.slot_number ASC
        ";

        $params[':student_id'] = $studentIdVal;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // Organize data by course -> week -> day
        $dataBySubject = [];
        
        foreach ($records as $row) {
            $courseName = (string)$row['course_name'];
            $subjectCode = (string)$row['subject_code'];
            $weekId = (int)$row['week_id'];
            $weekLabel = (string)$row['week_label'];
            $day = (string)$row['day_of_week'];
            $status = strtoupper((string)$row['status']);
            $startDate = (string)$row['start_date'];

            $subjectKey = $courseName . ' (' . $subjectCode . ')';

            if (!isset($dataBySubject[$subjectKey])) {
                $dataBySubject[$subjectKey] = [];
            }
            if (!isset($dataBySubject[$subjectKey][$weekId])) {
                $dataBySubject[$subjectKey][$weekId] = [
                    'label' => $weekLabel,
                    'start_date' => $startDate,
                    'days' => []
                ];
            }

            // Store by day (there might be multiple lectures same day)
            if (!isset($dataBySubject[$subjectKey][$weekId]['days'][$day])) {
                $dataBySubject[$subjectKey][$weekId]['days'][$day] = [];
            }

            $dataBySubject[$subjectKey][$weekId]['days'][$day][] = $status;
        }

        if (empty($dataBySubject)) {
            // No attendance data for this student - create empty sheet
            $sheetRows = [
                ['No attendance records found for ' . $studentName],
            ];
            $xlsx->addSheet(
                substr($studentName, 0, 31), // Excel sheet names max 31 chars
                $sheetRows,
                [
                    'colWidths' => [0 => 50],
                    'styleMap' => [0 => [0 => $xlsx->styleCell()]],
                ]
            );
            continue;
        }

        // Build sheet for this student
        // Layout: rows are subjects (left), columns are weeks with days
        $subjects = array_keys($dataBySubject);

        // Get all unique weeks across all subjects
        $allWeeks = [];
        foreach ($dataBySubject as $subjData) {
            foreach (array_keys($subjData) as $wk) {
                if (!isset($allWeeks[$wk])) {
                    $weekLabel = '';
                    foreach ($dataBySubject as $s) {
                        if (isset($s[$wk])) {
                            $weekLabel = $s[$wk]['label'];
                            break;
                        }
                    }
                    $allWeeks[$wk] = ['label' => $weekLabel, 'days' => []];
                }
            }
        }
        ksort($allWeeks);
        $weekIds = array_keys($allWeeks);

        // Collect all days per week
        foreach ($weekIds as $weekId) {
            $daysInWeek = [];
            foreach ($subjects as $subj) {
                if (isset($dataBySubject[$subj][$weekId])) {
                    foreach (array_keys($dataBySubject[$subj][$weekId]['days']) as $d) {
                        $daysInWeek[$d] = true;
                    }
                }
            }
            
            // Sort days
            $orderedDays = [];
            foreach (DAYS as $day) {
                if (isset($daysInWeek[$day])) {
                    $orderedDays[] = $day;
                }
            }
            $allWeeks[$weekId]['days'] = $orderedDays;
        }

        // Calculate total columns: 1 (subject name) + sum of days across all weeks
        $totalDays = 0;
        foreach ($allWeeks as $wk) {
            $totalDays += count($wk['days']);
        }
        $colCount = 1 + $totalDays;

        $sheetRows = [];
        $styleMap = [];
        $rowHeights = [];
        $merges = [];
        $currentRow = 0;

        // Header row 1: Week labels (merged across their days)
        $headerWeeks = ['Subject'];
        $colIdx = 1;
        foreach ($weekIds as $weekId) {
            $weekInfo = $allWeeks[$weekId];
            $dayCount = count($weekInfo['days']);
            if ($dayCount > 0) {
                $headerWeeks[] = $weekInfo['label'];
                // Merge across the days
                if ($dayCount > 1) {
                    $startCol = $colIdx;
                    $endCol = $colIdx + $dayCount - 1;
                    $merges[] = chr(65 + $startCol) . ($currentRow + 1) . ':' . chr(65 + $endCol) . ($currentRow + 1);
                }
                // Fill remaining columns for this week with empty
                for ($i = 1; $i < $dayCount; $i++) {
                    $headerWeeks[] = '';
                }
                $colIdx += $dayCount;
            }
        }
        $sheetRows[] = array_pad($headerWeeks, $colCount, '');
        $styleMap[$currentRow] = array_fill(0, $colCount, $xlsx->styleTitle());
        $rowHeights[$currentRow] = 25;
        $currentRow++;

        // Header row 2: Day names
        $headerDays = [''];
        foreach ($weekIds as $weekId) {
            $weekInfo = $allWeeks[$weekId];
            foreach ($weekInfo['days'] as $day) {
                $headerDays[] = $day;
            }
        }
        $sheetRows[] = array_pad($headerDays, $colCount, '');
        $styleMap[$currentRow] = array_fill(0, $colCount, $xlsx->styleHeader());
        $rowHeights[$currentRow] = 20;
        $currentRow++;

        // Data rows: one row per subject
        foreach ($subjects as $subj) {
            $row = [$subj];
            $rowStyle = [$xlsx->styleCellSmallBoldLeft()];

            foreach ($weekIds as $weekId) {
                $weekInfo = $allWeeks[$weekId];
                foreach ($weekInfo['days'] as $day) {
                    $statuses = $dataBySubject[$subj][$weekId]['days'][$day] ?? null;

                    if ($statuses === null) {
                        $row[] = '';
                        $rowStyle[] = $xlsx->styleCell(); // white/blank instead of grey
                    } else {
                        $hasPresent = in_array('PRESENT', $statuses, true);
                        $hasLate = in_array('LATE', $statuses, true);
                        $hasExcused = in_array('EXCUSED', $statuses, true);

                        if ($hasPresent) {
                            $row[] = 'P';
                            $rowStyle[] = $xlsx->styleFill('D1FAE5'); // green
                        } elseif ($hasLate) {
                            $row[] = 'L';
                            $rowStyle[] = $xlsx->styleFill('FEF3C7'); // yellow
                        } elseif ($hasExcused) {
                            $row[] = 'E';
                            $rowStyle[] = $xlsx->styleFill('DBEAFE'); // blue
                        } else {
                            $row[] = 'A';
                            $rowStyle[] = $xlsx->styleFill('FEE2E2'); // red
                        }
                    }
                }
            }

            $sheetRows[] = array_pad($row, $colCount, '');
            $styleMap[$currentRow] = $rowStyle;
            $rowHeights[$currentRow] = 25;
            $currentRow++;
        }

        // Column widths
        $colWidths = [0 => 35]; // Subject column (wider)
        for ($i = 1; $i < $colCount; $i++) {
            $colWidths[$i] = 8; // Day columns (narrower)
        }

        // Add sheet
        $xlsx->addSheet(
            substr($studentName, 0, 31), // Excel sheet names max 31 chars
            $sheetRows,
            [
                'colWidths' => $colWidths,
                'rowHeights' => $rowHeights,
                'styleMap' => $styleMap,
                'merges' => $merges,
                'freezeTopRows' => 2,
                'freezeLeftCols' => 1,
            ]
        );
    }

    // Output file
    $filename = 'student_attendance_year_' . $yearLevel . '_' . date('Y-m-d_His') . '.xlsx';

    $xlsx->download($filename);
    exit;
} catch (Throwable $e) {
    error_log('Export student tracking error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to generate Excel file: ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
