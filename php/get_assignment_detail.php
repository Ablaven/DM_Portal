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

// ─── Validation: assignment_id ───────────────────────────────────────────────
$assignmentId = isset($_GET['assignment_id']) ? filter_var($_GET['assignment_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ($assignmentId === null || $assignmentId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid assignment_id.']);
    exit;
}
$assignmentId = (int)$assignmentId;

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignments_table($pdo);
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in get_assignment_detail.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
    exit;
}

// ─── Fetch assignment ────────────────────────────────────────────────────────
$assignment = dmportal_get_assignment_by_id($pdo, $assignmentId);
if (!$assignment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assignment not found.']);
    exit;
}

// ─── Permission check ────────────────────────────────────────────────────────
if ($role === 'teacher') {
    $userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;
    if ($userDoctorId <= 0 || (int)$assignment['doctor_id'] !== $userDoctorId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized to view this assignment.']);
        exit;
    }
} elseif ($role === 'student') {
    // Students can only view assignments for courses they're enrolled in
    $studentId = isset($u['student_id']) && $u['student_id'] !== null ? (int)$u['student_id'] : 0;
    if ($studentId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Student account not found.']);
        exit;
    }
    
    $courseId = (int)$assignment['course_id'];
    if (!dmportal_is_student_enrolled_in_course($pdo, $studentId, $courseId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not enrolled in this course.']);
        exit;
    }
}
// Admin/management can view any assignment

// ─── Fetch submissions ───────────────────────────────────────────────────────
try {
    if ($role === 'student') {
        // Students see only their own submissions
        $studentId = (int)$u['student_id'];
        $stmt = $pdo->prepare(
            'SELECT sub.*, s.full_name AS student_name, s.email AS student_email'
            . ' FROM assignment_submissions sub'
            . ' JOIN students s ON s.student_id = sub.student_id'
            . ' WHERE sub.assignment_id = :assignment_id AND sub.student_id = :student_id'
            . ' ORDER BY sub.submitted_at DESC'
        );
        $stmt->execute([':assignment_id' => $assignmentId, ':student_id' => $studentId]);
    } else {
        // Teachers/admin see all submissions
        $stmt = $pdo->prepare(
            'SELECT sub.*, s.full_name AS student_name, s.email AS student_email'
            . ' FROM assignment_submissions sub'
            . ' JOIN students s ON s.student_id = sub.student_id'
            . ' WHERE sub.assignment_id = :assignment_id'
            . ' ORDER BY s.full_name ASC, sub.submitted_at DESC'
        );
        $stmt->execute([':assignment_id' => $assignmentId]);
    }
    
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out submissions where the physical file doesn't exist
    $validSubmissions = [];
    foreach ($submissions as $submission) {
        $storedFilename = (string)$submission['stored_filename'];
        $filePath = dmportal_get_assignment_stored_file_path($pdo, $assignmentId, $storedFilename);
        
        // Only include if the file physically exists
        if (is_file($filePath)) {
            $validSubmissions[] = $submission;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'assignment' => $assignment,
            'submissions' => $validSubmissions,
            'total_submissions' => count($validSubmissions),
        ],
    ]);
    
} catch (PDOException $e) {
    error_log('Database error fetching submissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch submissions.']);
}
