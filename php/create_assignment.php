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
function create_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Parse JSON body ─────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    create_error(400, 'Invalid JSON in request body.');
}

// ─── Validation: course_id ───────────────────────────────────────────────────
$courseId = filter_var($input['course_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($courseId === false || $courseId === null) {
    create_error(400, 'Missing or invalid course_id.');
}
$courseId = (int)$courseId;

// ─── Validation: title ───────────────────────────────────────────────────────
$title = trim((string)($input['title'] ?? ''));
if ($title === '') {
    create_error(400, 'Assignment title is required.');
}
if (mb_strlen($title) > 255) {
    create_error(400, 'Assignment title is too long (max 255 characters).');
}

// ─── Validation: description (optional) ──────────────────────────────────────
$description = isset($input['description']) ? trim((string)$input['description']) : null;
if ($description === '') {
    $description = null;
}

// ─── Validation: deadline ────────────────────────────────────────────────────
$deadlineStr = trim((string)($input['deadline'] ?? ''));
if ($deadlineStr === '') {
    create_error(400, 'Deadline is required.');
}
$deadlineTimestamp = strtotime($deadlineStr);
if ($deadlineTimestamp === false) {
    create_error(400, 'Invalid deadline format.');
}
// Ensure deadline is in the future
if ($deadlineTimestamp <= time()) {
    create_error(400, 'Deadline must be in the future.');
}
$deadline = date('Y-m-d H:i:s', $deadlineTimestamp);

// ─── Validation: allow_multiple_files ────────────────────────────────────────
$allowMultipleFiles = !empty($input['allow_multiple_files']);

// ─── Initialize PDO ──────────────────────────────────────────────────────────
try {
    $pdo = get_pdo();
    dmportal_ensure_assignments_table($pdo);
} catch (Throwable $e) {
    error_log('Database error in create_assignment.php: ' . $e->getMessage());
    create_error(500, 'Internal server error.');
}

// ─── Get course details (year_level, semester) ───────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT year_level, semester FROM courses WHERE course_id = :course_id LIMIT 1'
    );
    $stmt->execute([':course_id' => $courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        create_error(404, 'Course not found.');
    }
    
    $yearLevel = (int)$course['year_level'];
    $semester = (int)$course['semester'];
} catch (PDOException $e) {
    error_log('Database error fetching course: ' . $e->getMessage());
    create_error(500, 'Failed to fetch course details.');
}

// ─── Resolve doctor_id ───────────────────────────────────────────────────────
$userDoctorId = isset($u['doctor_id']) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : 0;

if ($role === 'teacher') {
    if ($userDoctorId <= 0) {
        create_error(403, 'Your account is not linked to a doctor record.');
    }

    // Check if teacher is assigned to this course
    if (!dmportal_is_doctor_assigned_to_course_assignment($pdo, $userDoctorId, $courseId)) {
        create_error(403, 'Not assigned to this course.');
    }

    $insertDoctorId = $userDoctorId;
} else {
    // admin / management
    if ($userDoctorId > 0) {
        $insertDoctorId = $userDoctorId;
    } else {
        // Admin without a doctor link: require an explicit doctor_id param
        $paramDoctorId = filter_var($input['doctor_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($paramDoctorId === false || $paramDoctorId === null) {
            create_error(400, 'Missing or invalid doctor_id. Admins without a linked doctor must supply a doctor_id.');
        }
        $insertDoctorId = (int)$paramDoctorId;
    }
}

// ─── Insert assignment ───────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO assignments'
        . ' (course_id, doctor_id, year_level, semester, title, description, deadline, allow_multiple_files)'
        . ' VALUES (:course_id, :doctor_id, :year_level, :semester, :title, :description, :deadline, :allow_multiple_files)'
    );
    
    $stmt->execute([
        ':course_id' => $courseId,
        ':doctor_id' => $insertDoctorId,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
        ':title' => $title,
        ':description' => $description,
        ':deadline' => $deadline,
        ':allow_multiple_files' => $allowMultipleFiles ? 1 : 0,
    ]);
    
    $assignmentId = (int)$pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment created successfully.',
        'data' => [
            'assignment_id' => $assignmentId,
            'course_id' => $courseId,
            'doctor_id' => $insertDoctorId,
            'year_level' => $yearLevel,
            'semester' => $semester,
            'title' => $title,
            'description' => $description,
            'deadline' => $deadline,
            'allow_multiple_files' => $allowMultipleFiles,
        ],
    ]);
    
} catch (PDOException $e) {
    error_log('Database error creating assignment: ' . $e->getMessage());
    create_error(500, 'Failed to create assignment.');
}
