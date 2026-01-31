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

$courseId = (int)($_POST['course_id'] ?? 0);
$courseName = trim((string)($_POST['course_name'] ?? ''));
$program = trim((string)($_POST['program'] ?? ''));
$yearLevel = (int)($_POST['year_level'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);
$courseType = trim((string)($_POST['course_type'] ?? ''));
$subjectCode = trim((string)($_POST['subject_code'] ?? ''));
$courseHours = (float)($_POST['course_hours'] ?? 0); // treated as total_hours
$coefficient = (float)($_POST['coefficient'] ?? 1);
$defaultRoomRaw = trim((string)($_POST['default_room_code'] ?? ''));
$defaultRoom = $defaultRoomRaw !== '' ? preg_replace('/\s+/', ' ', $defaultRoomRaw) : null;
$doctorIdRaw = $_POST['doctor_id'] ?? '';
$doctorId = ($doctorIdRaw === '' || $doctorIdRaw === null) ? null : (is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : -1);

if ($courseId <= 0) bad_request('course_id is required.');
if ($courseName === '') bad_request('course_name is required.');
if ($program === '') bad_request('program is required.');
if ($yearLevel < 1 || $yearLevel > 3) bad_request('year_level must be 1-3.');
if ($semester < 1 || $semester > 2) bad_request('semester must be 1-2.');
if (!in_array($courseType, ['R', 'LAS'], true)) bad_request('course_type must be R or LAS.');
// subject_code is REQUIRED and must be reasonably formatted.
if ($subjectCode === '') bad_request('subject_code is required.');
if (!preg_match('/^[A-Za-z0-9._-]{1,30}$/', $subjectCode)) bad_request('subject_code is invalid. Use letters/numbers and . _ - only (max 30 chars).');
if ($courseHours < 0 || $courseHours > 200) bad_request('course_hours must be 0-200.');
if ($coefficient <= 0 || $coefficient > 100) bad_request('coefficient must be a positive number.');
if ($defaultRoom !== null) {
    $defaultRoom = trim($defaultRoom);
    if (mb_strlen($defaultRoom) > 50) bad_request('default_room_code is too long (max 50 characters).');
}
if ($doctorId === -1) bad_request('doctor_id must be numeric or empty.');

$courseHours = round($courseHours, 2);
$coefficient = round($coefficient, 2);

try {
    $pdo = get_pdo();

    // course exists?
    $chk = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = :id');
    $chk->execute([':id' => $courseId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found.']);
        exit;
    }

    if ($doctorId !== null) {
        $chkD = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
        $chkD->execute([':id' => $doctorId]);
        if (!$chkD->fetch()) {
            bad_request('Doctor not found.');
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE courses
         SET course_name = :course_name,
             program = :program,
             year_level = :year_level,
             semester = :semester,
             course_type = :course_type,
             subject_code = :subject_code,
             total_hours = :total_hours,
             course_hours = :course_hours_legacy,
             coefficient = :coefficient,
             default_room_code = :default_room_code,
             doctor_id = :doctor_id
         WHERE course_id = :course_id'
    );

    // Detect total_hours change so we can invalidate any previous split allocations.
    $old = $pdo->prepare('SELECT total_hours FROM courses WHERE course_id = :id');
    $old->execute([':id' => $courseId]);
    $oldRow = $old->fetch();
    $oldTotal = $oldRow ? (float)$oldRow['total_hours'] : null;

    $stmt->execute([
        ':course_name' => $courseName,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
        ':course_type' => $courseType,
        ':subject_code' => $subjectCode,
        ':total_hours' => $courseHours,
        ':course_hours_legacy' => $courseHours,
        ':coefficient' => $coefficient,
        ':default_room_code' => $defaultRoom,
        ':doctor_id' => $doctorId,
        ':course_id' => $courseId,
    ]);

    // If total changed, clear existing allocations (they must be re-split to match).
    if ($oldTotal !== null && round($oldTotal, 2) !== round($courseHours, 2)) {
        try {
            $pdo->prepare('DELETE FROM course_doctor_hours WHERE course_id = :course_id')
                ->execute([':course_id' => $courseId]);
        } catch (Throwable $e) {
            // ignore if table missing
        }
    }

    // Keep course_doctors aligned with legacy single doctor_id if course_doctors exists.
    // This does NOT remove additional doctors; UI will manage the full list via set_course_doctors.php.
    try {
        $pdo->prepare('INSERT IGNORE INTO course_doctors (course_id, doctor_id) VALUES (:course_id, :doctor_id)')
            ->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    } catch (Throwable $e) {
        // ignore if table missing
    }

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('Duplicate key error while updating the course. If this persists, check your DB indexes/constraints.');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update course.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update course.']);
}
