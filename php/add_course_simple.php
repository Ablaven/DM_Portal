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

// Minimal inputs
$yearLevel = (int)($_POST['year_level'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);
$subjectCode = trim((string)($_POST['subject_code'] ?? ''));
$courseName = trim((string)($_POST['course_name'] ?? ''));
$doctorIdRaw = $_POST['doctor_id'] ?? '';
$doctorId = ($doctorIdRaw === '' || $doctorIdRaw === null) ? null : (is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : -1);

if ($yearLevel < 1 || $yearLevel > 3) bad_request('Year level must be 1, 2, or 3.');
if ($semester < 1 || $semester > 2) bad_request('Semester must be 1 or 2.');

if ($subjectCode === '') bad_request('Course code is required.');
if (!preg_match('/^[A-Za-z0-9._-]{1,30}$/', $subjectCode)) {
    bad_request('Course code is invalid. Use letters/numbers and . _ - only (max 30 chars).');
}

if ($courseName === '') bad_request('Course name is required.');
if (mb_strlen($courseName) > 200) bad_request('Course name is too long (max 200 chars).');

if ($doctorId === -1) bad_request('Doctor must be numeric or empty.');

// Defaults to match existing portal behaviour.
$program = 'Digital Marketing';
$courseType = 'R';
$totalHours = 10.00;
$defaultRoom = null;

try {
    $pdo = get_pdo();

    if ($doctorId !== null) {
        $chkD = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
        $chkD->execute([':id' => $doctorId]);
        if (!$chkD->fetch()) {
            bad_request('Selected doctor does not exist.');
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO courses (course_name, program, year_level, semester, course_type, subject_code, total_hours, course_hours, default_room_code, doctor_id)
         VALUES (:course_name, :program, :year_level, :semester, :course_type, :subject_code, :total_hours, :course_hours_legacy, :default_room_code, :doctor_id)'
    );

    $stmt->execute([
        ':course_name' => $courseName,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
        ':course_type' => $courseType,
        ':subject_code' => $subjectCode,
        ':total_hours' => $totalHours,
        ':course_hours_legacy' => $totalHours,
        ':default_room_code' => $defaultRoom,
        ':doctor_id' => $doctorId,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Best-effort: maintain course_doctors mapping when doctor is provided.
    if ($doctorId !== null) {
        try {
            $pdo->prepare('INSERT IGNORE INTO course_doctors (course_id, doctor_id) VALUES (:course_id, :doctor_id)')
                ->execute([':course_id' => $newId, ':doctor_id' => $doctorId]);
        } catch (Throwable $e) {
            // ignore if table missing
        }
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
    echo json_encode(['success' => false, 'error' => 'Failed to add course.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add course.']);
}
