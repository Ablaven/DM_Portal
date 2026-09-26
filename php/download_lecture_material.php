<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

// ─────────────────────────────────────────────────────────────────────────────
// Helper: emit a JSON error response and exit.
// Must only be called BEFORE any file-streaming headers have been sent.
// ─────────────────────────────────────────────────────────────────────────────
function json_error(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Auth - 401 if not logged in
// ─────────────────────────────────────────────────────────────────────────────
auth_require_login(true);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');

// ─────────────────────────────────────────────────────────────────────────────
// Validate material_id
// ─────────────────────────────────────────────────────────────────────────────
$materialId = filter_var($_GET['material_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($materialId === false || $materialId === null) {
    json_error(400, 'Missing or invalid material_id.');
}
$materialId = (int)$materialId;

try {
    $pdo = get_pdo();

    // Ensure schema exists (static guard; safe to call on every request)
    dmportal_ensure_lecture_materials_table($pdo);

    // ─────────────────────────────────────────────────────────────────────────
    // Fetch material row
    // ─────────────────────────────────────────────────────────────────────────
    $material = dmportal_get_material_by_id($pdo, $materialId);
    if ($material === null) {
        json_error(404, 'Material not found.');
    }

    $courseId = (int)$material['course_id'];

    // ─────────────────────────────────────────────────────────────────────────
    // Role-specific access checks
    // ─────────────────────────────────────────────────────────────────────────
    if ($role === 'teacher') {
        $doctorId = (int)($u['doctor_id'] ?? 0);
        if ($doctorId <= 0 || !dmportal_is_doctor_assigned_to_course($pdo, $doctorId, $courseId)) {
            json_error(403, 'Not assigned to this course.');
        }
    } elseif ($role === 'student') {
        $studentId = (int)($u['student_id'] ?? 0);
        if ($studentId <= 0) {
            json_error(403, 'Forbidden.');
        }

        // Fetch the course's academic context
        $courseStmt = $pdo->prepare(
            'SELECT program, year_level, semester FROM courses WHERE course_id = :course_id LIMIT 1'
        );
        $courseStmt->execute([':course_id' => $courseId]);
        $course = $courseStmt->fetch();
        if (!$course) {
            json_error(404, 'Material not found.');
        }

        // Fetch the student's academic context
        $stuStmt = $pdo->prepare(
            'SELECT program, year_level, semester FROM students WHERE student_id = :student_id LIMIT 1'
        );
        $stuStmt->execute([':student_id' => $studentId]);
        $student = $stuStmt->fetch();
        if (!$student) {
            json_error(403, 'Forbidden.');
        }

        // The course must match the student's program / year / semester
        if (
            (string)$course['program']  !== (string)$student['program']  ||
            (int)$course['year_level']  !== (int)$student['year_level']  ||
            (int)$course['semester']    !== (int)$student['semester']
        ) {
            json_error(403, 'Forbidden.');
        }
    }
    // admin / management: no ownership restriction

    // ─────────────────────────────────────────────────────────────────────────
    // Resolve file path and verify it exists
    // ─────────────────────────────────────────────────────────────────────────
    $storedFilename = (string)$material['stored_filename'];
    $filePath       = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);

    if (!is_file($filePath)) {
        json_error(404, 'File not found.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Determine Content-Type from file_type column
    // ─────────────────────────────────────────────────────────────────────────
    $fileType = (string)$material['file_type'];
    $contentType = match ($fileType) {
        'pdf'  => 'application/pdf',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        default => 'application/octet-stream',
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Build a safe Content-Disposition filename (RFC 5987 with ASCII fallback)
    // ─────────────────────────────────────────────────────────────────────────
    $originalFilename = (string)$material['original_filename'];

    // ASCII-only fallback: strip non-printable / non-ASCII bytes
    $asciiFilename = preg_replace('/[^\x20-\x7E]/', '_', $originalFilename);
    // Escape double-quotes in the ASCII fallback
    $asciiFilename = str_replace('"', '\\"', (string)$asciiFilename);

    // RFC 5987 encoded value (handles Unicode filenames)
    $encodedFilename = rawurlencode($originalFilename);

    $contentDisposition =
        'attachment; '
        . 'filename="' . $asciiFilename . '"; '
        . "filename*=UTF-8''" . $encodedFilename;

    // ─────────────────────────────────────────────────────────────────────────
    // Stream the file - NO JSON output after this point.
    // If an error occurs after headers are sent, abort silently.
    // ─────────────────────────────────────────────────────────────────────────
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $contentDisposition);
    header('Content-Length: ' . (string)(int)$material['file_size_bytes']);
    header('Cache-Control: private, no-cache');

    // Flush any output-buffered content so the raw file stream is not corrupted
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($filePath);

} catch (Throwable $e) {
    // Only emit JSON if headers have not been sent yet
    if (!headers_sent()) {
        json_error(500, 'Internal server error.');
    }
    // If headers were already sent, abort silently - do NOT corrupt the partial stream
}
