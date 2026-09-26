<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

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

// ─── Helpers ─────────────────────────────────────────────────────────────────
function upload_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Validation 1: course_id ─────────────────────────────────────────────────
$courseId = filter_var($_POST['course_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($courseId === false || $courseId === null) {
    upload_error(400, 'Missing or invalid course_id.');
}
$courseId = (int)$courseId;

// ─── Validation 2: teacher assignment check ───────────────────────────────────
// Resolve doctor_id for the inserting user:
//   - teacher:          must be their own linked doctor_id
//   - admin/management: use their own doctor_id if they have one;
//                       otherwise accept an explicit `doctor_id` POST param.
$userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;

if ($role === 'teacher') {
    if ($userDoctorId <= 0) {
        upload_error(403, 'Your account is not linked to a doctor record.');
    }

    // Defer $pdo initialisation until we need it for the first time.
    // We need it here for the assignment check.
    try {
        $pdo = get_pdo();
        dmportal_ensure_lecture_materials_table($pdo);
    } catch (Throwable $e) {
        upload_error(500, 'Internal server error.');
    }

    if (!dmportal_is_doctor_assigned_to_course($pdo, $userDoctorId, $courseId)) {
        upload_error(403, 'Not assigned to this course.');
    }

    $insertDoctorId = $userDoctorId;
} else {
    // admin / management
    if ($userDoctorId > 0) {
        // Admin has a linked doctor - use it directly.
        $insertDoctorId = $userDoctorId;
    } else {
        // Admin without a doctor link: require an explicit doctor_id param so the
        // lecture_materials.doctor_id FK (NOT NULL) can be satisfied.
        $paramDoctorId = filter_var($_POST['doctor_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($paramDoctorId === false || $paramDoctorId === null) {
            upload_error(400, 'Missing or invalid doctor_id. Admins without a linked doctor must supply a doctor_id.');
        }
        $insertDoctorId = (int)$paramDoctorId;
    }

    // For admin/management the $pdo may not have been created yet.
    if (!isset($pdo)) {
        try {
            $pdo = get_pdo();
            dmportal_ensure_lecture_materials_table($pdo);
        } catch (Throwable $e) {
            upload_error(500, 'Internal server error.');
        }
    }
}

// ─── Validation 3: file upload present ────────────────────────────────────────
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

// ─── Validation 4: extension check ────────────────────────────────────────────
$ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'pptx'], true)) {
    upload_error(422, 'Only PDF and PPTX files are allowed.');
}

// ─── Validation 5: MIME type check ────────────────────────────────────────────
$allowedMimes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);
if ($mimeType === false || !in_array($mimeType, $allowedMimes, true)) {
    upload_error(422, 'Only PDF and PPTX files are allowed.');
}

// ─── Validation 6: size check (≤ 50 MB) ──────────────────────────────────────
if ($fileSize > 52428800) {
    upload_error(422, 'File exceeds the 50 MB size limit.');
}

// ─── Generate stored filename ─────────────────────────────────────────────────
try {
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
} catch (Throwable $e) {
    upload_error(500, 'Internal server error.');
}

// ─── Ensure upload directory exists ──────────────────────────────────────────
if (!dmportal_ensure_upload_dir($pdo, $courseId)) {
    upload_error(500, 'Unable to create upload directory.');
}

$storedPath = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);

// ─── Move uploaded file to storage ───────────────────────────────────────────
if (!move_uploaded_file($tmpPath, $storedPath)) {
    upload_error(500, 'Failed to store file.');
}

// ─── Insert database record ───────────────────────────────────────────────────
$uploadedByUserId = (int)($u['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO lecture_materials'
        . ' (course_id, doctor_id, original_filename, stored_filename, file_type, file_size_bytes, uploaded_by_user_id)'
        . ' VALUES (:course_id, :doctor_id, :original_filename, :stored_filename, :file_type, :file_size_bytes, :uploaded_by_user_id)'
    );
    $stmt->execute([
        ':course_id'           => $courseId,
        ':doctor_id'           => $insertDoctorId,
        ':original_filename'   => $originalName,
        ':stored_filename'     => $storedFilename,
        ':file_type'           => $ext,
        ':file_size_bytes'     => $fileSize,
        ':uploaded_by_user_id' => $uploadedByUserId,
    ]);

    $materialId = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    // DB insert failed - clean up the already-moved file to avoid orphans.
    @unlink($storedPath);
    upload_error(500, 'Failed to save material record.');
}

// ─── Fetch and return the full material object ────────────────────────────────
$material = dmportal_get_material_by_id($pdo, $materialId);
if ($material === null) {
    // Very unlikely, but if the row can't be fetched after insert, return a
    // minimal object rather than erroring - the upload actually succeeded.
    $material = [
        'material_id'      => $materialId,
        'course_id'        => $courseId,
        'doctor_id'        => $insertDoctorId,
        'original_filename' => $originalName,
        'stored_filename'  => $storedFilename,
        'file_type'        => $ext,
        'file_size_bytes'  => $fileSize,
        'uploaded_by_user_id' => $uploadedByUserId,
        'created_at'       => date('Y-m-d H:i:s'),
        'uploader_name'    => '',
    ];
}

http_response_code(200);
echo json_encode(['success' => true, 'material' => $material]);
