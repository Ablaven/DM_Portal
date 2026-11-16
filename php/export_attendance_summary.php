<?php

declare(strict_types=1);

// Increase limits for exports
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '120');
set_time_limit(120);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    die($m);
}

try {
    error_log('[Attendance Summary Export] Starting export...');
    $pdo = get_pdo();

    // Get filters
    $yearLevel = isset($_GET['year_level']) && $_GET['year_level'] !== '' ? (int)$_GET['year_level'] : null;
    $studentId = isset($_GET['student_id']) && $_GET['student_id'] !== 'all' && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;
    $courseId = isset($_GET['course_id']) && $_GET['course_id'] !== 'all' && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : null;
    $termId = isset($_GET['term_id']) && $_GET['term_id'] !== '' ? (int)$_GET['term_id'] : null;
    $weekIdFrom = isset($_GET['week_id_from']) && $_GET['week_id_from'] !== '' ? (int)$_GET['week_id_from'] : null;
    $weekIdTo = isset($_GET['week_id_to']) && $_GET['week_id_to'] !== '' ? (int)$_GET['week_id_to'] : null;

    error_log('[Attendance Summary Export] Filters: yearLevel=' . $yearLevel . ', termId=' . $termId . ', weekFrom=' . $weekIdFrom . ', weekTo=' . $weekIdTo);

    if ($yearLevel === null) {
        bad_request('year_level is required');
    }

    // Build student list
    $students = [];
    if ($studentId !== null) {
        $stmt = $pdo->prepare('SELECT student_id, full_name, student_code FROM students WHERE student_id = :id AND year_level = :year LIMIT 1');
        $stmt->execute([':id' => $studentId, ':year' => $yearLevel]);
        $student = $stmt->fetch();
        if ($student) {
            $students[] = $student;
        }
    } else {
        $stmt = $pdo->prepare('SELECT student_id, full_name, student_code FROM students WHERE year_level = :year ORDER BY full_name ASC');
        $stmt->execute([':year' => $yearLevel]);
        $students = $stmt->fetchAll();
    }

    if (empty($students)) {
        error_log('[Attendance Summary Export] ERROR: No students found for year ' . $yearLevel);
        bad_request('No students found');
    }

    error_log('[Attendance Summary Export] Found ' . count($students) . ' students');

    // Build WHERE clauses for attendance query FIRST (needed for course filtering)
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

    $whereClause = implode(' AND ', $whereConditions);

    // Build course list - filter by term if provided
    $courses = [];
    if ($courseId !== null) {
        $stmt = $pdo->prepare('SELECT course_id, course_name, subject_code, semester FROM courses WHERE course_id = :id AND year_level = :year LIMIT 1');
        $stmt->execute([':id' => $courseId, ':year' => $yearLevel]);
        $course = $stmt->fetch();
        if ($course) {
            $courses[] = $course;
        }
    } else {
        // Filter courses by semester if term is selected
        $courseQuery = 'SELECT course_id, course_name, subject_code, semester FROM courses WHERE year_level = :year';
        $courseParams = [':year' => $yearLevel];
        
        if ($termId !== null) {
            // Get the term's semester
            $termStmt = $pdo->prepare('SELECT semester FROM terms WHERE term_id = :term_id');
            $termStmt->execute([':term_id' => $termId]);
            $termData = $termStmt->fetch();
            
            if ($termData && isset($termData['semester'])) {
                $semester = (int)$termData['semester'];
                $courseQuery .= ' AND semester = :semester';
                $courseParams[':semester'] = $semester;
                error_log('[Attendance Summary Export] Filtering courses by semester: ' . $semester);
            }
        }
        
        $courseQuery .= ' ORDER BY course_name ASC';
        $stmt = $pdo->prepare($courseQuery);
        $stmt->execute($courseParams);
        $courses = $stmt->fetchAll();
    }

    if (empty($courses)) {
        error_log('[Attendance Summary Export] ERROR: No courses found for year ' . $yearLevel);
        bad_request('No courses found for this year level');
    }

    error_log('[Attendance Summary Export] Found ' . count($courses) . ' courses');

    // Filter out courses that have NO attendance records for ANY student in the selected filters
    // This prevents showing columns full of N/A
    $coursesWithAttendance = [];
    foreach ($courses as $course) {
        $courseIdVal = (int)$course['course_id'];
        
        // Check if ANY student has attendance for this course
        $checkSql = "
            SELECT COUNT(*) as record_count
            FROM attendance_records ar
            JOIN doctor_schedules s ON s.schedule_id = ar.schedule_id
            JOIN weeks w ON w.week_id = s.week_id
            WHERE s.course_id = :course_id
              AND {$whereClause}
            LIMIT 1
        ";
        
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute(array_merge($params, [':course_id' => $courseIdVal]));
        $checkResult = $checkStmt->fetch();
        
        if ((int)($checkResult['record_count'] ?? 0) > 0) {
            $coursesWithAttendance[] = $course;
        } else {
            error_log('[Attendance Summary Export] Skipping course ' . $course['course_name'] . ' - no attendance records in selected period');
        }
    }
    
    if (empty($coursesWithAttendance)) {
        error_log('[Attendance Summary Export] ERROR: No courses with attendance data');
        bad_request('No courses have attendance data for the selected filters');
    }
    
    $courses = $coursesWithAttendance;
    error_log('[Attendance Summary Export] After filtering: ' . count($courses) . ' courses with attendance data');

    // Create XLSX
    $xlsx = new SimpleXlsxWriter();

    // Group students by year level (in case filtering spans multiple years - but shouldn't)
    // For now we just create one sheet per year level
    $sheetName = "Year $yearLevel";

    // Build the summary table
    // Rows: Students
    // Columns: Subjects + Total
    
    $sheetRows = [];
    $styleMap = [];
    $rowHeights = [];
    $currentRow = 0;

    // Header row: Student | Subject1 | Subject2 | ... | Total
    $headerRow = ['Student'];
    foreach ($courses as $course) {
        $headerRow[] = $course['course_name'] . ' (' . $course['subject_code'] . ')';
    }
    $headerRow[] = 'Total';
    $sheetRows[] = $headerRow;
    $headerStyles = [];
    for ($i = 0; $i < count($headerRow); $i++) {
        $headerStyles[$i] = $xlsx->styleHeader();
    }
    $styleMap[$currentRow] = $headerStyles;
    $rowHeights[$currentRow] = 22;
    $currentRow++;

    // Data rows: one per student
    foreach ($students as $student) {
        $studentIdVal = (int)$student['student_id'];
        $studentName = (string)($student['full_name'] ?? 'Student');
        $studentCode = (string)($student['student_code'] ?? '');
        
        $row = [$studentName . ' (' . $studentCode . ')'];
        $rowStyles = [0 => $xlsx->styleCell()];
        $totalPresent = 0;
        $totalSessions = 0;

        $colIdx = 1; // Start after student name column
        foreach ($courses as $course) {
            $courseIdVal = (int)$course['course_id'];

            // Get attendance stats for this student + course
            // FIXED: Don't filter by year_level in attendance query, we already filtered courses
            $sql = "
                SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN ar.status = 'PRESENT' THEN 1 ELSE 0 END) as present_count
                FROM attendance_records ar
                JOIN doctor_schedules s ON s.schedule_id = ar.schedule_id
                JOIN weeks w ON w.week_id = s.week_id
                WHERE ar.student_id = :student_id
                  AND s.course_id = :course_id
                  AND {$whereClause}
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($params, [
                ':student_id' => $studentIdVal,
                ':course_id' => $courseIdVal
            ]));
            $stats = $stmt->fetch();

            $sessions = (int)($stats['total_sessions'] ?? 0);
            $present = (int)($stats['present_count'] ?? 0);

            if ($sessions === 0) {
                error_log("[Attendance Summary] Student {$studentIdVal} has 0 sessions for course {$courseIdVal} ({$course['course_name']})");
            }

            $totalSessions += $sessions;
            $totalPresent += $present;

            // Calculate percentage
            if ($sessions > 0) {
                $percentage = round(($present / $sessions) * 100, 1);
                $row[] = $percentage . '%';
                
                // Color code: green if >50%, red if <=50%
                if ($percentage > 50) {
                    $rowStyles[$colIdx] = $xlsx->styleFill('D4EDDA'); // Light green
                } else {
                    $rowStyles[$colIdx] = $xlsx->styleFill('F8D7DA'); // Light red
                }
            } else {
                $row[] = 'N/A';
                $rowStyles[$colIdx] = $xlsx->styleCell(); // No color for N/A
            }
            
            $colIdx++;
        }

        // Calculate total percentage
        if ($totalSessions > 0) {
            $totalPercentage = round(($totalPresent / $totalSessions) * 100, 1);
            $row[] = $totalPercentage . '%';
            
            // Color code total column
            if ($totalPercentage > 50) {
                $rowStyles[$colIdx] = $xlsx->styleFillBold('C3E6CB'); // Darker green + bold
            } else {
                $rowStyles[$colIdx] = $xlsx->styleFillBold('F5C6CB'); // Darker red + bold
            }
        } else {
            $row[] = 'N/A';
            $rowStyles[$colIdx] = $xlsx->styleCell();
        }

        $sheetRows[] = $row;
        $styleMap[$currentRow] = $rowStyles;
        $rowHeights[$currentRow] = 50; // Increased to 50px for more space
        $currentRow++;
    }

    // Calculate column widths
    $colWidths = [];
    $colWidths[0] = 35; // Student name column (wider)
    for ($i = 1; $i < count($headerRow) - 1; $i++) {
        $colWidths[$i] = 25; // Subject columns
    }
    $colWidths[count($headerRow) - 1] = 15; // Total column (narrower)

    // Add sheet
    $xlsx->addSheet(
        $sheetName,
        $sheetRows,
        [
            'colWidths' => $colWidths,
            'styleMap' => $styleMap,
            'rowHeights' => $rowHeights,
            'freezePane' => 'B2' // Freeze first column and first row
        ]
    );

    // Build filename
    $filename = 'Attendance_Summary_Year' . $yearLevel;
    if ($termId) {
        $filename .= '_Term' . $termId;
    }
    if ($weekIdFrom && $weekIdTo) {
        $filename .= '_Week' . $weekIdFrom . '-' . $weekIdTo;
    }
    $filename .= '_' . date('Ymd_His') . '.xlsx';

    // Send file
    error_log('[Attendance Summary Export] Sending file: ' . $filename);
    $xlsx->download($filename);
    error_log('[Attendance Summary Export] Export completed successfully');

} catch (Exception $e) {
    error_log('[Attendance Summary Export] EXCEPTION: ' . $e->getMessage());
    error_log('[Attendance Summary Export] Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die('Error generating report: ' . $e->getMessage());
}
