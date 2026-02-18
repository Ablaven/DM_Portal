<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $message): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function safe_rollback(?PDO $pdo): void {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$doctorIdRaw = $_POST['doctor_id'] ?? '';
$day = trim((string)($_POST['day_of_week'] ?? ''));
$slotRaw = $_POST['slot_number'] ?? '';
$courseIdRaw = $_POST['course_id'] ?? ''; // can be empty for remove
$roomCodeRaw = trim((string)($_POST['room_code'] ?? '')); // legacy room_code
$countsTowardsHours = isset($_POST['counts_towards_hours']) ? (int)((bool)$_POST['counts_towards_hours']) : 1;
$extraMinutesRaw = $_POST['extra_minutes'] ?? 0;
$extraMinutes = is_numeric($extraMinutesRaw) ? (int)$extraMinutesRaw : 0;
$action = trim((string)($_POST['action'] ?? 'set')); // set | remove

$weekIdRaw = $_POST['week_id'] ?? '';
$weekId = is_numeric($weekIdRaw) ? (int)$weekIdRaw : 0;

$doctorId = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;
$slot = is_numeric($slotRaw) ? (int)$slotRaw : 0;
$courseId = ($courseIdRaw !== '' && is_numeric($courseIdRaw)) ? (int)$courseIdRaw : 0;
$roomCode = $roomCodeRaw !== '' ? trim($roomCodeRaw) : null;
if ($roomCode !== null) {
    // Allow free text, but keep it safe/consistent
    $roomCode = preg_replace('/\s+/', ' ', $roomCode);
    $roomCode = trim($roomCode);
    if (mb_strlen($roomCode) > 50) {
        bad_request('room_code is too long (max 50 characters).');
    }
}

$validDays = ['Sun','Mon','Tue','Wed','Thu'];
if ($doctorId <= 0) bad_request('doctor_id is required.');
if (!in_array($day, $validDays, true)) bad_request('Invalid day_of_week.');
if ($slot < 1 || $slot > 5) bad_request('slot_number must be 1-5.');
if (!in_array($action, ['set','remove'], true)) bad_request('Invalid action.');
if ($extraMinutes < 0 || $extraMinutes > 45 || ($extraMinutes % 15) !== 0) bad_request('extra_minutes must be 0,15,30,45.');
if ($action === 'set' && $courseId <= 0) bad_request('course_id is required for set.');
// Note: if your DB was imported from an older schema, you MUST add doctor_schedules.room_code and doctor_schedules.counts_towards_hours columns.

// Base slot hours do not change.
$slotHours = 1.5;
$extraHours = $extraMinutes / 60.0;
$deductHours = $slotHours + $extraHours;

try {
    $pdo = get_pdo();

    // Ensure schema exists for upgraded DBs (DDL must be outside transaction).
    dmportal_ensure_schedule_extra_minutes_column($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_POST);

    $pdo->beginTransaction();

    // Default to active week if week_id not provided
    if ($weekId <= 0) {
        $stmt = $pdo->prepare("SELECT week_id FROM weeks WHERE status='active' AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
        $stmt->execute([':term_id' => $termId]);
        $wk = $stmt->fetch();
        if (!$wk) {
            safe_rollback($pdo);
            bad_request('No active week for this term. Start a week first.');
        }
        $weekId = (int)$wk['week_id'];
    }

    // Prevent scheduling on cancelled days
    $cchk = $pdo->prepare('SELECT cancellation_id FROM doctor_week_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day');
    $cchk->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
    if ($cchk->fetch()) {
        safe_rollback($pdo);
        bad_request('This day is cancelled for the selected doctor.');
    }

    // Prevent scheduling on cancelled slots (optional table)
    try {
        $scchk = $pdo->prepare('SELECT slot_cancellation_id FROM doctor_slot_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
        $scchk->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
        if ($scchk->fetch()) {
            safe_rollback($pdo);
            bad_request('This slot is cancelled for the selected doctor.');
        }
    } catch (PDOException $e) {
        // MySQL: 1146 = table doesn't exist
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
            throw $e;
        }
        // If table missing, skip slot-cancellation enforcement.
    }

    // Prevent scheduling on unavailable time ranges
    // Slot times (must match JS):
    // 1) 08:30–10:00
    // 2) 10:10–11:30
    // 3) 11:40–13:00
    // 4) 13:10–14:40
    // 5) 14:50–16:20
    $slotStarts = [
        1 => '08:30:00',
        2 => '10:10:00',
        3 => '11:40:00',
        4 => '13:10:00',
        5 => '14:50:00',
    ];
    $slotEnds = [
        1 => '10:00:00',
        2 => '11:30:00',
        3 => '13:00:00',
        4 => '14:40:00',
        5 => '16:20:00',
    ];

    $wkStmt = $pdo->prepare('SELECT start_date FROM weeks WHERE week_id = :id');
    $wkStmt->execute([':id' => $weekId]);
    $wkRow = $wkStmt->fetch();

    if ($wkRow && !empty($wkRow['start_date'])) {
        $startDate = new DateTimeImmutable($wkRow['start_date']);
        $offsetDays = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4][$day] ?? null;
        if ($offsetDays !== null) {
            $slotDate = $startDate->modify('+' . $offsetDays . ' days')->format('Y-m-d');
            $slotStart = $slotDate . ' ' . ($slotStarts[$slot] ?? '00:00:00');
            $slotEnd = $slotDate . ' ' . ($slotEnds[$slot] ?? '00:00:00');

            // doctor_unavailability is optional (older DB imports may not have it)
            try {
                $uChk = $pdo->prepare(
                    'SELECT unavailability_id FROM doctor_unavailability
                     WHERE doctor_id = :doctor_id
                       AND start_datetime < :slot_end
                       AND end_datetime > :slot_start
                     LIMIT 1'
                );
                $uChk->execute([
                    ':doctor_id' => $doctorId,
                    ':slot_start' => $slotStart,
                    ':slot_end' => $slotEnd,
                ]);
                if ($uChk->fetch()) {
                    safe_rollback($pdo);
                    bad_request('Doctor is unavailable during this slot.');
                }
            } catch (PDOException $e) {
                // MySQL: 1146 = table doesn't exist
                if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
                    throw $e;
                }
                // If table is missing, skip unavailability enforcement.
            }
        }
    }

    // Lock relevant schedule row for update
    $existingStmt = $pdo->prepare(
        'SELECT schedule_id, course_id, room_code FROM doctor_schedules
         WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot
         FOR UPDATE'
    );
    $existingStmt->execute([
        ':week_id' => $weekId,
        ':doctor_id' => $doctorId,
        ':day' => $day,
        ':slot' => $slot,
    ]);
    $existing = $existingStmt->fetch();
    $existingCourseId = $existing ? (int)$existing['course_id'] : 0;
    $existingRoomCode = $existing ? ($existing['room_code'] ?? null) : null;

    // Course remaining hours are now computed dynamically from total_hours and scheduled slots.
    // We no longer mutate courses.course_Hours here.

    if ($action === 'remove') {
        if ($existing) {
            $del = $pdo->prepare('DELETE FROM doctor_schedules WHERE schedule_id = :sid');
            $del->execute([':sid' => (int)$existing['schedule_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'data' => ['removed' => true]]);
        exit;
    }

    // action = set
    // validate doctor exists
    $chkD = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chkD->execute([':id' => $doctorId]);
    if (!$chkD->fetch()) {
        safe_rollback($pdo);
        bad_request('Doctor not found.');
    }

    // validate course exists + lock it for update
    $chkC = $pdo->prepare('SELECT course_id, total_hours, course_hours, program, year_level, semester FROM courses WHERE course_id = :id FOR UPDATE');
    $chkC->execute([':id' => $courseId]);
    $courseRow = $chkC->fetch();
    if (!$courseRow) {
        safe_rollback($pdo);
        bad_request('Course not found.');
    }

    // Enforce that the selected course belongs to the selected doctor.
    // Prefer the course_doctors mapping when available; fallback to legacy courses.doctor_id.
    $belongsOk = false;

    // 1) Try course_doctors mapping (multi-doctor support)
    try {
        $belongs = $pdo->prepare('SELECT 1 FROM course_doctors WHERE course_id = :course_id AND doctor_id = :doctor_id');
        $belongs->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
        $belongsOk = (bool)$belongs->fetchColumn();
    } catch (Throwable $e) {
        // ignore if table missing
        $belongsOk = false;
    }

    // 2) Fallback: legacy single-doctor assignment on courses table
    if (!$belongsOk) {
        $legacyBelongs = $pdo->prepare('SELECT 1 FROM courses WHERE course_id = :course_id AND doctor_id = :doctor_id');
        $legacyBelongs->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
        $belongsOk = (bool)$legacyBelongs->fetchColumn();
    }

    if (!$belongsOk) {
        safe_rollback($pdo);
        bad_request('This course is not assigned to the selected doctor.');
    }

    // Room conflict prevention:
    // For the same week/day/slot, the same room cannot be used by another schedule.
    // Use schedule_id exclusion to correctly support editing the existing slot.
    if ($roomCode !== null) {
        if ($existing) {
            $roomChk = $pdo->prepare(
                "SELECT schedule_id
                 FROM doctor_schedules
                 WHERE week_id = :week_id
                   AND day_of_week = :day
                   AND slot_number = :slot
                   AND room_code = :room_code
                   AND schedule_id <> :sid
                 LIMIT 1"
            );
            $roomChk->execute([
                ':week_id' => $weekId,
                ':day' => $day,
                ':slot' => $slot,
                ':room_code' => $roomCode,
                ':sid' => (int)$existing['schedule_id'],
            ]);
        } else {
            $roomChk = $pdo->prepare(
                "SELECT schedule_id
                 FROM doctor_schedules
                 WHERE week_id = :week_id
                   AND day_of_week = :day
                   AND slot_number = :slot
                   AND room_code = :room_code
                 LIMIT 1"
            );
            $roomChk->execute([
                ':week_id' => $weekId,
                ':day' => $day,
                ':slot' => $slot,
                ':room_code' => $roomCode,
            ]);
        }
        if ($roomChk->fetch()) {
            safe_rollback($pdo);
            bad_request('Room conflict: this room is already used in this slot.');
        }
    }

    // Student conflict prevention:
    // For the same week/day/slot, there must not already be another scheduled course
    // with the same (program, year_level, semester) under a different doctor.
    // NOTE: PDO MySQL (with ATTR_EMULATE_PREPARES=false) does NOT allow repeating the same
    // named placeholder multiple times. Keep each placeholder unique.
    $conflictCheck = $pdo->prepare(
        "SELECT s.schedule_id
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         WHERE s.week_id = :week_id
           AND s.day_of_week = :day
           AND s.slot_number = :slot
           AND c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :semester
           AND s.doctor_id <> :doctor_id"
    );

    $conflictCheck->execute([
        ':week_id' => $weekId,
        ':day' => $day,
        ':slot' => $slot,
        ':program' => $courseRow['program'],
        ':year_level' => (int)$courseRow['year_level'],
        ':semester' => (int)$courseRow['semester'],
        ':doctor_id' => $doctorId,
    ]);

    $conflict = $conflictCheck->fetch();
    if ($conflict) {
        safe_rollback($pdo);
        bad_request('Student timetable conflict: another lecture for the same Program/Year/Semester already exists in this slot.');
    }

    // If slot was empty OR course changed, ensure the course has enough remaining hours.
    // Remaining hours = total_hours - scheduled_slots*1.5 (excluding cancelled days)
    if (!$existing || $existingCourseId !== $courseId) {
        // total hours
        $total = isset($courseRow['total_hours']) ? (float)$courseRow['total_hours'] : (float)$courseRow['course_hours'];

        // count scheduled slots for this course (exclude cancellations)
        // doctor_slot_cancellations is optional; if missing, fall back to day-cancellation-only logic.
        try {
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) AS c
                 FROM doctor_schedules s
                 LEFT JOIN doctor_week_cancellations cw
                   ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
                 LEFT JOIN doctor_slot_cancellations cs
                   ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
                 WHERE s.course_id = :course_id
                   AND s.counts_towards_hours = 1
                   AND cw.cancellation_id IS NULL
                   AND cs.slot_cancellation_id IS NULL"
            );
            $cntStmt->execute([':course_id' => $courseId]);
            $countRow = $cntStmt->fetch();
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) AS c
                 FROM doctor_schedules s
                 LEFT JOIN doctor_week_cancellations cw
                   ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
                 WHERE s.course_id = :course_id
                   AND s.counts_towards_hours = 1
                   AND cw.cancellation_id IS NULL"
            );
            $cntStmt->execute([':course_id' => $courseId]);
            $countRow = $cntStmt->fetch();
        }
        $scheduledSlots = (int)($countRow['c'] ?? 0);

        // if we are overwriting the same slot, do not double-count it
        if ($existing && $existingCourseId === $courseId) {
            // no-op
        }

        // Remaining must cover the amount this save will deduct.
        $remaining = $total - ($scheduledSlots * $slotHours);
        if ($remaining < $deductHours) {
            safe_rollback($pdo);
            bad_request('Not enough remaining hours for this course (including extra minutes).');
        }
    }

    if ($existing) {
        $upd = $pdo->prepare('UPDATE doctor_schedules SET course_id = :course_id, room_code = :room_code, counts_towards_hours = :cth, extra_minutes = :extra_minutes WHERE schedule_id = :sid');
        $upd->execute([':course_id' => $courseId, ':room_code' => $roomCode, ':cth' => $countsTowardsHours ? 1 : 0, ':extra_minutes' => $extraMinutes, ':sid' => (int)$existing['schedule_id']]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO doctor_schedules (week_id, doctor_id, course_id, day_of_week, slot_number, room_code, counts_towards_hours, extra_minutes)
             VALUES (:week_id, :doctor_id, :course_id, :day, :slot, :room_code, :cth, :extra_minutes)'
        );
        $ins->execute([
            ':week_id' => $weekId,
            ':doctor_id' => $doctorId,
            ':course_id' => $courseId,
            ':day' => $day,
            ':slot' => $slot,
            ':room_code' => $roomCode,
            ':cth' => $countsTowardsHours ? 1 : 0,
            ':extra_minutes' => $extraMinutes,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'data' => ['saved' => true, 'term_id' => $termId]]);
} catch (PDOException $e) {
    safe_rollback($pdo ?? null);

    // Duplicate slot (shouldn't happen due to FOR UPDATE, but handle anyway)
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('This slot is already taken.');
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update schedule.',
        'details' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    safe_rollback($pdo ?? null);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update schedule.',
        'details' => $e->getMessage(),
    ]);
}
