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

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$weekId = isset($_GET['week_id']) ? (int)$_GET['week_id'] : 0;
$day = trim((string)($_GET['day_of_week'] ?? ''));
$slot = isset($_GET['slot_number']) ? (int)$_GET['slot_number'] : 0;
$roomCodeRaw = trim((string)($_GET['room_code'] ?? ''));
$roomCode = $roomCodeRaw !== '' ? trim($roomCodeRaw) : null;

// room_code is optional (used only for room-conflict checks)
if ($roomCode !== null) {
    $roomCode = preg_replace('/\s+/', ' ', $roomCode);
    $roomCode = trim($roomCode);
    if (mb_strlen($roomCode) > 50) bad_request('room_code is too long (max 50 characters).');
}

$validDays = ['Sun','Mon','Tue','Wed','Thu'];
if ($doctorId <= 0) bad_request('doctor_id is required.');
// course_id can be 0 when opening a cancelled slot just to undo/cancel status.
// In that case, we skip program/year/semester conflict checks.
if ($courseId < 0) bad_request('course_id is invalid.');
if ($weekId <= 0) bad_request('week_id is required.');
if (!in_array($day, $validDays, true)) bad_request('Invalid day_of_week.');
if ($slot < 1 || $slot > 5) bad_request('slot_number must be 1-5.');
// Free-text room_code allowed; keep only length validation (done above).

try {
    $pdo = get_pdo();

    $course = null;
    if ($courseId > 0) {
        // Load course meta
        $c = $pdo->prepare('SELECT program, year_level, semester FROM courses WHERE course_id = :id');
        $c->execute([':id' => $courseId]);
        $course = $c->fetch();
        if (!$course) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Course not found.']);
            exit;
        }
    }

    // Cancellation check (day)
    $cancel = $pdo->prepare('SELECT cancellation_id, reason FROM doctor_week_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day');
    $cancel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
    $cancelRow = $cancel->fetch();

    // Cancellation check (slot) (optional table)
    $slotCancelRow = null;
    try {
        $slotCancel = $pdo->prepare('SELECT slot_cancellation_id, reason FROM doctor_slot_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
        $slotCancel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
        $slotCancelRow = $slotCancel->fetch();
    } catch (Throwable $e) {
        // ignore if table missing
        $slotCancelRow = null;
    }

    $conflict = null;
    if ($course) {
        // Conflict check (same program/year/semester in same slot in week)
        $conflictCheck = $pdo->prepare(
            "SELECT s.schedule_id, s.doctor_id, d.full_name AS doctor_name, c.course_id, c.course_name
             FROM doctor_schedules s
             JOIN courses c ON c.course_id = s.course_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE s.week_id = :week_id
               AND s.day_of_week = :day
               AND s.slot_number = :slot
               AND c.program = :program
               AND c.year_level = :year_level
               AND c.semester = :semester
               AND NOT (s.doctor_id = :doctor_id)"
        );

        $conflictCheck->execute([
            ':week_id' => $weekId,
            ':day' => $day,
            ':slot' => $slot,
            ':program' => $course['program'],
            ':year_level' => (int)$course['year_level'],
            ':semester' => (int)$course['semester'],
            ':doctor_id' => $doctorId,
        ]);

        $conflict = $conflictCheck->fetch();
    }


    // Room conflict check (optional)
    // Use schedule_id exclusion to avoid false positives while editing the same slot.
    $roomConflict = null;
    if ($roomCode !== null) {
        $sidStmt = $pdo->prepare(
            'SELECT schedule_id FROM doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot LIMIT 1'
        );
        $sidStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
        $currentSid = (int)($sidStmt->fetchColumn() ?: 0);

        $rStmt = $pdo->prepare(
            "SELECT s.schedule_id, s.doctor_id, d.full_name AS doctor_name
             FROM doctor_schedules s
             JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE s.week_id = :week_id
               AND s.day_of_week = :day
               AND s.slot_number = :slot
               AND s.room_code = :room_code
               AND (:sid = 0 OR s.schedule_id <> :sid)
             LIMIT 1"
        );
        $rStmt->execute([
            ':week_id' => $weekId,
            ':day' => $day,
            ':slot' => $slot,
            ':room_code' => $roomCode,
            ':sid' => $currentSid,
        ]);
        $roomConflict = $rStmt->fetch() ?: false;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'cancelled' => $cancelRow ? true : false,
            'cancellation_reason' => $cancelRow['reason'] ?? null,
            'slot_cancelled' => $slotCancelRow ? true : false,
            'slot_cancellation_reason' => $slotCancelRow['reason'] ?? null,
            'conflict' => $conflict ? true : false,
            'conflict_with' => $conflict ? [
                'doctor_id' => (int)$conflict['doctor_id'],
                'doctor_name' => $conflict['doctor_name'],
                'course_id' => (int)$conflict['course_id'],
                'course_name' => $conflict['course_name'],
            ] : null,
            'room_conflict' => $roomConflict ? true : false,
            'room_conflict_with' => $roomConflict ? [
                'doctor_id' => (int)$roomConflict['doctor_id'],
                'doctor_name' => (string)$roomConflict['doctor_name'],
                'room_code' => $roomCode,
            ] : null,
        ],
    ]);
} catch (PDOException $e) {
    // Provide actionable errors for schema mismatches (common with older imported DBs)
    $errno = (int)($e->errorInfo[1] ?? 0);
    if ($errno === 1146) {
        // table missing
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database table is missing. Please apply the latest SQL migrations (doctor_slot_cancellations and/or doctor_schedules columns).',
            'details' => $e->getMessage(),
        ]);
        exit;
    }
    if ($errno === 1054) {
        // unknown column
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database column is missing (likely doctor_schedules.room_code). Please apply the latest SQL migrations.',
            'details' => $e->getMessage(),
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to check conflict.', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to check conflict.', 'details' => $e->getMessage()]);
}
