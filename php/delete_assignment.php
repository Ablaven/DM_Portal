<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_assignment_helpers.php';

// ─── Auth ────────────────────────────────────────────────────────────────────
auth_require_roles(['teacher', 'admin', 'management'], true);

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

// ─── Validation: assignment_id ───────────────────────────────────────────────
$assignmentId = filter_var($input['assignment_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($assignmentId === false || $assignmentId === null) {
    delete_error(400, 'Missing or invalid assignment_id.');
}
$assignmentId = (int)$assignmentId;

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignments_table($pdo);
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in delete_assignment.php: ' . $e->getMessage());
    delete_error(500, 'Internal server error.');
}

// ─── Fetch assignment ────────────────────────────────────────────────────────
$assignment = dmportal_get_assignment_by_id($pdo, $assignmentId);
if (!$assignment) {
    delete_error(404, 'Assignment not found.');
}

// ─── Permission check ────────────────────────────────────────────────────────
$userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;

if ($role === 'teacher') {
    // Teachers can only delete their own assignments
    if ($userDoctorId <= 0 || (int)$assignment['doctor_id'] !== $userDoctorId) {
        delete_error(403, 'You can only delete your own assignments.');
    }
}
// Admin/management can delete any assignment

// ─── Get all submissions for this assignment ─────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT * FROM assignment_submissions WHERE assignment_id = :assignment_id'
    );
    $stmt->execute([':assignment_id' => $assignmentId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete physical files for all submissions
    foreach ($submissions as $submission) {
        dmportal_delete_assignment_submission_file($pdo, $submission);
    }
    
} catch (PDOException $e) {
    error_log('Error fetching submissions for deletion: ' . $e->getMessage());
    // Continue with deletion even if file cleanup fails
}

// ─── Delete assignment (CASCADE will delete submissions) ─────────────────────
try {
    $stmt = $pdo->prepare('DELETE FROM assignments WHERE assignment_id = :assignment_id');
    $stmt->execute([':assignment_id' => $assignmentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment deleted successfully.',
    ]);
    
} catch (PDOException $e) {
    error_log('Database error deleting assignment: ' . $e->getMessage());
    delete_error(500, 'Failed to delete assignment.');
}
