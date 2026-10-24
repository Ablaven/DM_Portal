<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_assignment_helpers.php';

// ─── Auth ────────────────────────────────────────────────────────────────────
auth_require_roles(['teacher', 'admin', 'management', 'student'], true);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignments_table($pdo);
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in get_assignments.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
    exit;
}

// ─── Parse filters ───────────────────────────────────────────────────────────
$courseId = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$yearLevel = isset($_GET['year_level']) ? filter_var($_GET['year_level'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$semester = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$doctorId = isset($_GET['doctor_id']) ? filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

// ─── Build query based on role ───────────────────────────────────────────────
$whereClauses = [];
$params = [];

if ($role === 'teacher') {
    // Teachers see only their own assignments
    $userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;
    if ($userDoctorId <= 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    $whereClauses[] = 'a.doctor_id = :user_doctor_id';
    $params[':user_doctor_id'] = $userDoctorId;
} elseif ($role === 'student') {
    // Students see assignments for courses they're enrolled in
    $studentId = isset($u['student_id']) && $u['student_id'] !== null ? (int)$u['student_id'] : 0;
    if ($studentId <= 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    // Get student's program, year, semester
    $stmt = $pdo->prepare('SELECT program, year_level, semester FROM students WHERE student_id = :student_id LIMIT 1');
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    $whereClauses[] = 'c.program = :program';
    $whereClauses[] = 'a.year_level = :year';
    $whereClauses[] = 'a.semester = :semester';
    $params[':program'] = $student['program'];
    $params[':year'] = $student['year_level'];
    $params[':semester'] = $student['semester'];
}
// Admin/management see all assignments (no additional filters unless specified)

// ─── Apply optional filters ──────────────────────────────────────────────────
if ($courseId !== null && $courseId !== false) {
    $whereClauses[] = 'a.course_id = :course_id';
    $params[':course_id'] = $courseId;
}

if ($yearLevel !== null && $yearLevel !== false) {
    $whereClauses[] = 'a.year_level = :year_level';
    $params[':year_level'] = $yearLevel;
}

if ($semester !== null && $semester !== false) {
    $whereClauses[] = 'a.semester = :semester_filter';
    $params[':semester_filter'] = $semester;
}

if ($doctorId !== null && $doctorId !== false) {
    $whereClauses[] = 'a.doctor_id = :doctor_id';
    $params[':doctor_id'] = $doctorId;
}

// ─── Build and execute query ─────────────────────────────────────────────────
$whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = 
    'SELECT a.*, c.course_name, c.subject_code, c.program, d.full_name AS doctor_name,'
    . ' (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.assignment_id) AS submission_count,'
    . ' (SELECT COUNT(*) FROM students s WHERE s.program = c.program AND s.year_level = a.year_level AND s.semester = a.semester) AS total_students'
    . ' FROM assignments a'
    . ' JOIN courses c ON c.course_id = a.course_id'
    . ' JOIN doctors d ON d.doctor_id = a.doctor_id'
    . ' ' . $whereSQL
    . ' ORDER BY a.deadline ASC';

try {
    // Debug logging
    error_log('get_assignments.php - Role: ' . $role);
    error_log('get_assignments.php - SQL: ' . $sql);
    error_log('get_assignments.php - Params: ' . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('get_assignments.php - Found ' . count($assignments) . ' assignments');
    
    // For students, add submission status for each assignment
    if ($role === 'student' && isset($studentId)) {
        foreach ($assignments as &$assignment) {
            $assignmentId = (int)$assignment['assignment_id'];
            $submissions = dmportal_get_student_submissions($pdo, $assignmentId, $studentId);
            $assignment['student_submission_count'] = count($submissions);
            $assignment['student_has_submitted'] = count($submissions) > 0;
            $assignment['student_submissions'] = $submissions;
        }
        unset($assignment);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $assignments,
    ]);
    
} catch (PDOException $e) {
    error_log('Database error fetching assignments: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch assignments.']);
}
