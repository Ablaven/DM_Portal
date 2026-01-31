<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $message): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$courseName  = trim((string)($_POST['course_name'] ?? ''));
$program     = trim((string)($_POST['program'] ?? ''));
$yearLevel   = (int)($_POST['year_level'] ?? 1);
$semester    = (int)($_POST['semester'] ?? 1);
$courseType  = trim((string)($_POST['course_type'] ?? ''));
$subjectCode = trim((string)($_POST['subject_code'] ?? ''));
$courseHours = (float)($_POST['course_hours'] ?? 10); // treated as total_hours
$coefficient = (float)($_POST['coefficient'] ?? 1);
$defaultRoomRaw = trim((string)($_POST['default_room_code'] ?? ''));
$defaultRoom = $defaultRoomRaw !== '' ? preg_replace('/\s+/', ' ', $defaultRoomRaw) : null;
$doctorIdRaw = $_POST['doctor_id'] ?? '';
$doctorId    = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;

if ($courseName === '') {
    bad_request('Course name is required.');
}

if ($program === '') {
    bad_request('Program is required.');
}

if ($yearLevel < 1 || $yearLevel > 3) {
    bad_request('Year level must be 1, 2, or 3.');
}

if ($semester < 1 || $semester > 2) {
    bad_request('Semester must be 1 or 2.');
}

if (!in_array($courseType, ['R', 'LAS'], true)) {
    bad_request('Course type must be R or LAS.');
}

// subject_code is REQUIRED and must be reasonably formatted.
// Allow digits, letters, dot, dash, underscore (e.g. 3.014, DM-101).
if ($subjectCode === '') {
    bad_request('Subject code is required.');
}
if (!preg_match('/^[A-Za-z0-9._-]{1,30}$/', $subjectCode)) {
    bad_request('subject_code is invalid. Use letters/numbers and . _ - only (max 30 chars).');
}

if ($courseHours <= 0 || $courseHours > 200) {
    bad_request('Course hours must be a positive number.');
}

if ($coefficient <= 0 || $coefficient > 100) {
    bad_request('coefficient must be a positive number.');
}

if ($defaultRoom !== null) {
    $defaultRoom = trim($defaultRoom);
    if (mb_strlen($defaultRoom) > 50) {
        bad_request('default_room_code is too long (max 50 characters).');
    }
}

// Normalize to 2 decimal places (DECIMAL(5,2))
$courseHours = round($courseHours, 2);
$coefficient = round($coefficient, 2);

// For backward compatibility, a primary doctor is still required.
if ($doctorId <= 0) {
    bad_request('Assigned doctor is required.');
}

try {
    $pdo = get_pdo();

    // Ensure doctor exists
    $check = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :doctor_id');
    $check->execute([':doctor_id' => $doctorId]);
    if (!$check->fetch()) {
        bad_request('Selected doctor does not exist.');
    }

    // Insert course
    $stmt = $pdo->prepare(
        'INSERT INTO courses (course_name, program, year_level, semester, course_type, subject_code, total_hours, course_hours, coefficient, default_room_code, doctor_id)
         VALUES (:course_name, :program, :year_level, :semester, :course_type, :subject_code, :total_hours, :course_hours_legacy, :coefficient, :default_room_code, :doctor_id)'
    );

    $stmt->execute([
        ':course_name' => $courseName,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
        ':course_type' => $courseType,
        ':subject_code' => $subjectCode,
        ':total_hours' => $courseHours,
        // keep legacy column in sync for older UI/queries
        ':course_hours_legacy' => $courseHours,
        ':coefficient' => $coefficient,
        ':default_room_code' => $defaultRoom,
        ':doctor_id' => $doctorId,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Add to course_doctors (multi-doctor mapping)
    try {
        $pdo->prepare('INSERT IGNORE INTO course_doctors (course_id, doctor_id) VALUES (:course_id, :doctor_id)')
            ->execute([':course_id' => $newId, ':doctor_id' => $doctorId]);
    } catch (Throwable $e) {
        // If table doesn't exist (older DB), ignore; UI will fallback to courses.doctor_id
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'course_id' => $newId,
        ],
    ]);
} catch (PDOException $e) {
    // Duplicate key (should be rare after allowing duplicate course codes)
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('Duplicate key error while adding the course. If this persists, check your DB indexes/constraints.');
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add course.',
        // 'debug' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add course.',
        // 'debug' => $e->getMessage(),
    ]);
}
