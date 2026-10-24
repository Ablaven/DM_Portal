<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_assignment_helpers.php';

// ─── Auth ────────────────────────────────────────────────────────────────────
auth_require_roles(['student', 'teacher', 'admin', 'management'], true);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');

// ─── Helper ──────────────────────────────────────────────────────────────────
function download_error(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Validation: submission_id ───────────────────────────────────────────────
$submissionId = isset($_GET['submission_id']) ? filter_var($_GET['submission_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ($submissionId === null || $submissionId === false) {
    download_error(400, 'Missing or invalid submission_id.');
}
$submissionId = (int)$submissionId;

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignment_submissions_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in download_assignment_submission.php: ' . $e->getMessage());
    download_error(500, 'Internal server error.');
}

// ─── Fetch submission ────────────────────────────────────────────────────────
$submission = dmportal_get_submission_by_id($pdo, $submissionId);
if (!$submission) {
    download_error(404, 'Submission not found.');
}

// ─── Permission check ────────────────────────────────────────────────────────
$assignmentId = (int)$submission['assignment_id'];
$assignment = dmportal_get_assignment_by_id($pdo, $assignmentId);

if (!$assignment) {
    download_error(404, 'Assignment not found.');
}

if ($role === 'student') {
    // Students can only download their own submissions
    $studentId = isset($u['student_id']) && $u['student_id'] !== null ? (int)$u['student_id'] : 0;
    if ($studentId <= 0 || (int)$submission['student_id'] !== $studentId) {
        download_error(403, 'You can only download your own submissions.');
    }
} elseif ($role === 'teacher') {
    // Teachers can only download submissions for their own assignments
    $userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;
    if ($userDoctorId <= 0 || (int)$assignment['doctor_id'] !== $userDoctorId) {
        download_error(403, 'You can only download submissions for your own assignments.');
    }
}
// Admin/management can download any submission

// ─── Get file path ───────────────────────────────────────────────────────────
$storedFilename = (string)$submission['stored_filename'];
$filePath = dmportal_get_assignment_stored_file_path($pdo, $assignmentId, $storedFilename);

if (!file_exists($filePath) || !is_readable($filePath)) {
    download_error(404, 'File not found on server.');
}

// ─── Serve file ──────────────────────────────────────────────────────────────
$originalFilename = (string)$submission['original_filename'];
$fileSize = (int)$submission['file_size_bytes'];
$fileType = (string)$submission['file_type'];

// Determine MIME type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
];
$mimeType = $mimeTypes[$fileType] ?? 'application/octet-stream';

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Send headers
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($originalFilename) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);
exit;
