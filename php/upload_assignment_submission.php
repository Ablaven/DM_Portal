<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_assignment_helpers.php';

// ─── Auth ────────────────────────────────────────────────────────────────────
auth_require_roles(['student'], true);

$u = auth_current_user();
$studentId = isset($u['student_id']) && $u['student_id'] !== null ? (int)$u['student_id'] : 0;

if ($studentId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Student account not found.']);
    exit;
}

// ─── Method guard ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ─── Helper ──────────────────────────────────────────────────────────────────
function upload_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Validation 1: assignment_id ─────────────────────────────────────────────
$assignmentId = filter_var($_POST['assignment_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($assignmentId === false || $assignmentId === null) {
    upload_error(400, 'Missing or invalid assignment_id.');
}
$assignmentId = (int)$assignmentId;

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignments_table($pdo);
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in upload_assignment_submission.php: ' . $e->getMessage());
    upload_error(500, 'Internal server error.');
}

// ─── Fetch assignment ────────────────────────────────────────────────────────
$assignment = dmportal_get_assignment_by_id($pdo, $assignmentId);
if (!$assignment) {
    upload_error(404, 'Assignment not found.');
}

// ─── Check enrollment ────────────────────────────────────────────────────────
$courseId = (int)$assignment['course_id'];
if (!dmportal_is_student_enrolled_in_course($pdo, $studentId, $courseId)) {
    upload_error(403, 'Not enrolled in this course.');
}

// ─── Check deadline ──────────────────────────────────────────────────────────
if (dmportal_is_assignment_deadline_passed($pdo, $assignmentId)) {
    upload_error(403, 'Assignment deadline has passed. Submissions are no longer accepted.');
}

// ─── Check multiple files setting ────────────────────────────────────────────
$allowMultipleFiles = !empty($assignment['allow_multiple_files']);
$existingSubmissionCount = dmportal_count_student_submissions($pdo, $assignmentId, $studentId);

if (!$allowMultipleFiles && $existingSubmissionCount > 0) {
    // Single file only - delete existing submission and replace
    $existingSubmissions = dmportal_get_student_submissions($pdo, $assignmentId, $studentId);
    foreach ($existingSubmissions as $sub) {
        // Delete physical file
        dmportal_delete_assignment_submission_file($pdo, $sub);
        // Delete database record
        $stmt = $pdo->prepare('DELETE FROM assignment_submissions WHERE submission_id = :submission_id');
        $stmt->execute([':submission_id' => $sub['submission_id']]);
    }
}

// ─── Validation 2: file upload present ───────────────────────────────────────
if (
    !isset($_FILES['file']) ||
    !is_array($_FILES['file']) ||
    ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
) {
    upload_error(400, 'No file received or upload error.');
}

$tmpPath      = (string)($_FILES['file']['tmp_name'] ?? '');
$originalName = (string)($_FILES['file']['name']     ?? '');
$fileSize     = (int)($_FILES['file']['size']         ?? 0);

// ─── Validation 3: extension check ───────────────────────────────────────────
$ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'pptx', 'zip', 'rar'], true)) {
    upload_error(422, 'Only PDF, PPTX, ZIP, and RAR files are allowed.');
}

// ─── Validation 4: MIME type check ───────────────────────────────────────────
$allowedMimes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-zip-compressed',
    'application/x-rar-compressed',
    'application/x-rar',
    'application/vnd.rar',
    'application/octet-stream', // Generic binary for zip/rar
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);
if ($mimeType === false || !in_array($mimeType, $allowedMimes, true)) {
    upload_error(422, 'Only PDF, PPTX, ZIP, and RAR files are allowed.');
}

// ─── Validation 5: size check (≤ 50 MB) ──────────────────────────────────────
if ($fileSize > 52428800) {
    upload_error(422, 'File exceeds the 50 MB size limit.');
}

// ─── Generate stored filename ────────────────────────────────────────────────
try {
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
} catch (Throwable $e) {
    upload_error(500, 'Internal server error.');
}

// ─── Ensure upload directory exists ──────────────────────────────────────────
if (!dmportal_ensure_assignment_upload_dir($pdo, $assignmentId)) {
    upload_error(500, 'Unable to create upload directory.');
}

$storedPath = dmportal_get_assignment_stored_file_path($pdo, $assignmentId, $storedFilename);

// ─── Move uploaded file to storage ───────────────────────────────────────────
if (!move_uploaded_file($tmpPath, $storedPath)) {
    upload_error(500, 'Failed to store file.');
}

// ─── Insert database record ──────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO assignment_submissions'
        . ' (assignment_id, student_id, original_filename, stored_filename, file_type, file_size_bytes)'
        . ' VALUES (:assignment_id, :student_id, :original_filename, :stored_filename, :file_type, :file_size_bytes)'
    );
    
    $stmt->execute([
        ':assignment_id' => $assignmentId,
        ':student_id' => $studentId,
        ':original_filename' => $originalName,
        ':stored_filename' => $storedFilename,
        ':file_type' => $ext,
        ':file_size_bytes' => $fileSize,
    ]);
    
    $submissionId = (int)$pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'File submitted successfully.',
        'data' => [
            'submission_id' => $submissionId,
            'assignment_id' => $assignmentId,
            'student_id' => $studentId,
            'original_filename' => $originalName,
            'stored_filename' => $storedFilename,
            'file_type' => $ext,
            'file_size_bytes' => $fileSize,
        ],
    ]);
    
} catch (PDOException $e) {
    // Delete physical file if database insert fails
    if (file_exists($storedPath)) {
        @unlink($storedPath);
    }
    error_log('Database error submitting assignment: ' . $e->getMessage());
    upload_error(500, 'Failed to submit assignment.');
}
