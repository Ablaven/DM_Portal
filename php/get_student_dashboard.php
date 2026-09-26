<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/PerformanceMetricsCalculator.php';

// Authentication and authorization
auth_require_login(true);
auth_require_roles(['student'], true);

$user = auth_current_user();
$studentId = (int)($user['student_id'] ?? 0);

if ($studentId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden.']);
    exit;
}

try {
    $pdo = get_pdo();

    // Step 1: Detect active term from weeks table
    // Query joins weeks -> terms -> academic_years to get full term details
    $termStmt = $pdo->prepare(
        "SELECT w.term_id, t.label, t.semester, ay.label as academic_year
         FROM weeks w
         JOIN terms t ON t.term_id = w.term_id
         JOIN academic_years ay ON ay.academic_year_id = t.academic_year_id
         WHERE w.status = 'active'
         LIMIT 1"
    );
    $termStmt->execute();
    $termRow = $termStmt->fetch();

    // Handle case where no active term exists
    if (!$termRow) {
        echo json_encode([
            'success' => true,
            'data' => [
                'term' => null,
                'message' => 'No active term is currently set.'
            ]
        ]);
        exit;
    }

    $termId = (int)$termRow['term_id'];
    $termSemester = (int)$termRow['semester'];
    
    $termData = [
        'term_id' => $termId,
        'label' => $termRow['label'],
        'semester' => $termSemester,
        'academic_year' => $termRow['academic_year']
    ];

    // Step 2: Query student profile
    $studentStmt = $pdo->prepare(
        "SELECT student_id, full_name, year_level, semester, program
         FROM students
         WHERE student_id = :student_id"
    );
    $studentStmt->execute([':student_id' => $studentId]);
    $studentRow = $studentStmt->fetch();

    if (!$studentRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }

    $studentData = [
        'student_id' => (int)$studentRow['student_id'],
        'full_name' => $studentRow['full_name'],
        'year_level' => (int)$studentRow['year_level'],
        'semester' => (int)$studentRow['semester'],
        'program' => $studentRow['program']
    ];

    // Step 3: Fetch grades with course information
    // Query courses matching student's year_level, program, and semester
    // LEFT JOIN with evaluation_grades to get final scores
    // Filter by active term's semester
    // Include courses with doctor_id matching or doctor_id=0
    $gradesStmt = $pdo->prepare(
        "SELECT 
            c.course_id,
            c.course_name,
            c.course_type,
            c.subject_code,
            c.year_level,
            c.semester,
            eg.final_score,
            eg.grade_id
         FROM courses c
         LEFT JOIN evaluation_grades eg 
           ON eg.course_id = c.course_id 
           AND eg.student_id = :student_id 
           AND eg.term_id = :term_id
         WHERE c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :student_semester
           AND (c.doctor_id IS NOT NULL OR c.doctor_id = 0)
         ORDER BY c.course_name ASC"
    );
    
    $gradesStmt->execute([
        ':student_id' => $studentId,
        ':term_id' => $termId,
        ':program' => $studentData['program'],
        ':year_level' => $studentData['year_level'],
        ':student_semester' => $studentData['semester']
    ]);
    
    $gradesRows = $gradesStmt->fetchAll();
    
    // Format grades data with "N/A" for missing grades
    $grades = [];
    foreach ($gradesRows as $row) {
        $finalScore = $row['final_score'];
        $hasGrade = $finalScore !== null;
        
        $grades[] = [
            'course_id' => (int)$row['course_id'],
            'course_name' => $row['course_name'],
            'course_type' => $row['course_type'],
            'subject_code' => $row['subject_code'],
            'final_score' => $hasGrade ? (float)$finalScore : null,
            'final_score_display' => $hasGrade ? number_format((float)$finalScore, 2) : 'N/A',
            'has_grade' => $hasGrade
        ];
    }

    // Step 4: Count total scheduled classes for student's courses
    // Query doctor_schedules joining through weeks to filter by term_id
    // and joining courses to match student's program/year_level/semester
    // Requirement 4.1: Count total scheduled classes for the student in the active term
    // Requirement 9.2: Use aggregate functions (COUNT) for efficiency
    // Requirement 9.3: Limit queries to only the active term_id
    $totalScheduledStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT ds.schedule_id) as total_scheduled
         FROM doctor_schedules ds
         JOIN weeks w ON w.week_id = ds.week_id
         JOIN courses c ON c.course_id = ds.course_id
         WHERE w.term_id = :term_id
           AND c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :student_semester"
    );
    
    $totalScheduledStmt->execute([
        ':term_id' => $termId,
        ':program' => $studentData['program'],
        ':year_level' => $studentData['year_level'],
        ':student_semester' => $studentData['semester']
    ]);
    
    $scheduledRow = $totalScheduledStmt->fetch();
    $totalScheduled = (int)($scheduledRow['total_scheduled'] ?? 0);

    // Step 5: Aggregate attendance records by status
    // Count PRESENT, ABSENT, and late (if exists) for the student
    // JOIN with doctor_schedules and courses to ensure we only count
    // attendance for the student's enrolled courses
    // Requirement 4.2: Count classes marked as 'present'
    // Requirement 4.3: Count classes marked as 'late'
    // Requirement 4.4: Count classes marked as 'absent'
    // Requirement 9.2: Use aggregate functions (COUNT) instead of fetching all records
    $attendanceStmt = $pdo->prepare(
        "SELECT 
            COUNT(CASE WHEN ar.status = 'PRESENT' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'ABSENT' THEN 1 END) as absent_count,
            COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count
         FROM attendance_records ar
         JOIN doctor_schedules ds ON ds.schedule_id = ar.schedule_id
         JOIN weeks w ON w.week_id = ds.week_id
         JOIN courses c ON c.course_id = ds.course_id
         WHERE ar.student_id = :student_id
           AND ar.term_id = :term_id
           AND c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :student_semester"
    );
    
    $attendanceStmt->execute([
        ':student_id' => $studentId,
        ':term_id' => $termId,
        ':program' => $studentData['program'],
        ':year_level' => $studentData['year_level'],
        ':student_semester' => $studentData['semester']
    ]);
    
    $attendanceRow = $attendanceStmt->fetch();
    $presentCount = (int)($attendanceRow['present_count'] ?? 0);
    $absentCount = (int)($attendanceRow['absent_count'] ?? 0);
    $lateCount = (int)($attendanceRow['late_count'] ?? 0);

    // Step 6: Calculate attendance rate
    // attendance_rate = (present_count / total_scheduled) Ã— 100
    // Round to one decimal place
    // Handle division by zero (no scheduled classes)
    // Requirement 4.5: Compute attendance_rate as (present_count / total_scheduled_count) Ã— 100
    // Requirement 4.7: Handle zero attendance records scenario
    // Requirement 4.8: Round attendance_rate to one decimal place
    $attendanceRate = 0.0;
    if ($totalScheduled > 0) {
        $attendanceRate = round(($presentCount / $totalScheduled) * 100, 1);
    }

    $attendanceData = [
        'total_scheduled' => $totalScheduled,
        'present_count' => $presentCount,
        'absent_count' => $absentCount,
        'late_count' => $lateCount,
        'attendance_rate' => $attendanceRate
    ];

    // Step 7: Calculate performance metrics using PerformanceMetricsCalculator
    // Requirement 5.1: Compute average grade across all courses with recorded grades
    // Requirement 5.2: Identify the highest grade from all courses
    // Requirement 5.3: Identify the lowest grade from all courses
    // Requirement 5.4: Count the number of courses with grades above 85
    // Requirement 5.5: Count the number of courses with grades below 60
    // Requirement 5.7: When fewer than two courses have grades, do not compute highest and lowest
    
    $performanceData = PerformanceMetricsCalculator::calculate($grades);

    // Return complete dashboard data
    echo json_encode([
        'success' => true,
        'data' => [
            'term' => $termData,
            'student' => $studentData,
            'grades' => $grades,
            'attendance' => $attendanceData,
            'performance' => $performanceData
        ]
    ]);

} catch (Throwable $e) {
    error_log("Dashboard error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch dashboard data.'
    ]);
}
