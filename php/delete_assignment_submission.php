<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_assignment_helpers.php';

// ─── Auth ────────────────────────────────────────────────────────────────────
auth_require_roles(['student', 'teacher', 'admin', 'management'], true);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');

// ─── Method guard ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ─── Helper ──────────────────────────────────────────────────────────────────
function delete_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Parse JSON body ─────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    delete_error(400, 'Invalid JSON in request body.');
}

// ─── Validation: submission_id ───────────────────────────────────────────────
$submissionId = filter_var($input['submission_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($submissionId === false || $submissionId === null) {
    delete_error(400, 'Missing or invalid submission_id.');
}
$submissionId = (int)$submissionId;

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in delete_assignment_submission.php: ' . $e->getMessage());
    delete_error(500, 'Internal server error.');
}

// ─── Fetch submission ────────────────────────────────────────────────────────
$submission = dmportal_get_submission_by_id($pdo, $submissionId);
if (!$submission) {
    delete_error(404, 'Submission not found.');
}

// ─── Permission check ────────────────────────────────────────────────────────
if ($role === 'student') {
    $studentId = isset($u['student_id']) && $u['student_id'] !== null ? (int)$u['student_id'] : 0;
    if ($studentId <= 0 || (int)$submission['student_id'] !== $studentId) {
        delete_error(403, 'You can only delete your own submissions.');
    }
    
    // Students cannot delete after deadline
    $assignmentId = (int)$submission['assignment_id'];
    if (dmportal_is_assignment_deadline_passed($pdo, $assignmentId)) {
        delete_error(403, 'Cannot delete submission after deadline has passed.');
    }
} elseif ($role === 'teacher') {
    // Teachers can only delete submissions for their own assignments
    $userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;
    $assignmentId = (int)$submission['assignment_id'];
    $assignment = dmportal_get_assignment_by_id($pdo, $assignmentId);
    
    if (!$assignment || $userDoctorId <= 0 || (int)$assignment['doctor_id'] !== $userDoctorId) {
        delete_error(403, 'You can only delete submissions for your own assignments.');
    }
}
// Admin/management can delete any submission

// ─── Delete physical file ────────────────────────────────────────────────────
dmportal_delete_assignment_submission_file($pdo, $submission);

// ─── Delete database record ──────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('DELETE FROM assignment_submissions WHERE submission_id = :submission_id');
    $stmt->execute([':submission_id' => $submissionId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission deleted successfully.',
    ]);
    
} catch (PDOException $e) {
    error_log('Database error deleting submission: ' . $e->getMessage());
    delete_error(500, 'Failed to delete submission.');
}
