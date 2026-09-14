<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

// ---------------------------------------------------------------------------
// Auth — 401 if not logged in
// ---------------------------------------------------------------------------
auth_require_login(true);

$user = auth_current_user();
$role = (string)($user['role'] ?? '');

// ---------------------------------------------------------------------------
// Validate material_id
// ---------------------------------------------------------------------------
$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
if ($materialId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Missing or invalid material_id.']);
    exit;
}

// ---------------------------------------------------------------------------
// Fetch material record
// ---------------------------------------------------------------------------
$pdo = get_pdo();
dmportal_ensure_lecture_materials_table($pdo);

$material = dmportal_get_material_by_id($pdo, $materialId);
if ($material === null) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Material not found.']);
    exit;
}

$courseId = (int)$material['course_id'];
$doctorId = (int)$material['doctor_id'];

// ---------------------------------------------------------------------------
// Role-based access checks
// ---------------------------------------------------------------------------
if ($role === 'teacher') {
    // Teacher must be assigned to the course that owns this material
    $ownDoctorId = (int)($user['doctor_id'] ?? 0);
    if ($ownDoctorId <= 0 || !dmportal_is_doctor_assigned_to_course($pdo, $ownDoctorId, $courseId)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Not assigned to this course.']);
        exit;
    }
} elseif ($role === 'student') {
    // Student must share program / year_level / semester with the material's course
    $studentId = (int)($user['student_id'] ?? 0);
    if ($studentId <= 0) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }

    // Fetch student context
    $stStmt = $pdo->prepare(
        'SELECT program, year_level, semester FROM students WHERE student_id = :sid LIMIT 1'
    );
    $stStmt->execute([':sid' => $studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }

    // Fetch the course row to compare
    $cStmt = $pdo->prepare(
        'SELECT program, year_level, semester FROM courses WHERE course_id = :cid LIMIT 1'
    );
    $cStmt->execute([':cid' => $courseId]);
    $course = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$course
        || (string)$course['program']    !== (string)$student['program']
        || (int)$course['year_level']    !== (int)$student['year_level']
        || (int)$course['semester']      !== (int)$student['semester']
    ) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }
}
// admin / management have no additional restrictions beyond auth_require_login

// ---------------------------------------------------------------------------
// Resolve stored file path
// ---------------------------------------------------------------------------
$storedFilename = (string)$material['stored_filename'];
$filePath = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);

if (!is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'File not found.']);
    exit;
}

// ---------------------------------------------------------------------------
// Stream file with inline Content-Disposition
// ---------------------------------------------------------------------------
$fileType       = (string)$material['file_type'];
$originalName   = (string)$material['original_filename'];
$fileSizeBytes  = (int)$material['file_size_bytes'];

$mimeType = $fileType === 'pdf'
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

// Encode the filename for the Content-Disposition header (RFC 5987)
$encodedName = rawurlencode($originalName);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes($originalName) . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Length: ' . $fileSizeBytes);
header('Cache-Control: private, no-cache');

// Flush any output buffering before streaming
if (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($filePath);
exit;
