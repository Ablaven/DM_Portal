<?php

declare(strict_types=1);

require_once __DIR__ . '/_slot_time_helpers.php';

function dmportal_ensure_attendance_sessions_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_sessions (\n"
        ."  term_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  schedule_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  opened_by_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,\n"
        ."  hours_counted TINYINT(1) NOT NULL DEFAULT 0,\n"
        ."  PRIMARY KEY (term_id, schedule_id),\n"
        ."  KEY idx_attendance_sessions_schedule (schedule_id),\n"
        ."  KEY idx_attendance_sessions_doctor (doctor_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    dmportal_backfill_attendance_sessions($pdo);
    dmportal_repair_attendance_session_hours_counted($pdo);
}

/**
 * Grandfather hours for schedules that already have attendance saved before the
 * lecture-window rule existed. New lectures still require a teacher session during
 * the Cairo window (admin-only saves do not create a session row).
 */
function dmportal_repair_attendance_session_hours_counted(PDO $pdo): void
{
    try {
        $pdo->exec(
            'UPDATE attendance_sessions ash
             INNER JOIN (
               SELECT DISTINCT term_id, schedule_id
               FROM attendance_records
             ) ar ON ar.term_id = ash.term_id AND ar.schedule_id = ash.schedule_id
             SET ash.hours_counted = 1
             WHERE ash.hours_counted = 0'
        );
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1146) {
            return;
        }
        throw $e;
    }
}

function dmportal_backfill_attendance_sessions(PDO $pdo): void
{
    $marker = $pdo->query("SELECT 1 FROM attendance_sessions LIMIT 1");
    if ($marker && $marker->fetchColumn()) {
        if ($marker) {
            $marker->closeCursor();
        }
        return;
    }
    if ($marker) {
        $marker->closeCursor();
    }

    try {
        $stmt = $pdo->query(
            'SELECT ar.term_id, ar.schedule_id, MIN(ar.created_at) AS first_saved
             FROM attendance_records ar
             GROUP BY ar.term_id, ar.schedule_id'
        );
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1146) {
            return;
        }
        throw $e;
    }

    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!$rows) {
        return;
    }

    $schedStmt = $pdo->prepare(
        'SELECT s.schedule_id, s.doctor_id
         FROM doctor_schedules s
         WHERE s.schedule_id = :sid
         LIMIT 1'
    );

    $insert = $pdo->prepare(
        'INSERT INTO attendance_sessions (term_id, schedule_id, doctor_id, opened_at, opened_by_user_id, hours_counted)
         VALUES (:term_id, :schedule_id, :doctor_id, :opened_at, 0, :hours_counted)'
    );

    foreach ($rows as $row) {
        $scheduleId = (int)$row['schedule_id'];
        $termId = (int)$row['term_id'];
        $firstSaved = (string)$row['first_saved'];

        $schedStmt->execute([':sid' => $scheduleId]);
        $sched = $schedStmt->fetch();
        if (!$sched) {
            continue;
        }

        // Backfill only runs for slots that already have attendance_records.
        $hoursCounted = 1;

        $insert->execute([
            ':term_id' => $termId,
            ':schedule_id' => $scheduleId,
            ':doctor_id' => (int)$sched['doctor_id'],
            ':opened_at' => $firstSaved !== '' ? $firstSaved : date('Y-m-d H:i:s'),
            ':hours_counted' => $hoursCounted,
        ]);
    }
}

/**
 * @return array{term_id:int,schedule_id:int,doctor_id:int,opened_at:string,opened_by_user_id:int,hours_counted:int}|null
 */
function dmportal_get_attendance_session(PDO $pdo, int $termId, int $scheduleId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT term_id, schedule_id, doctor_id, opened_at, opened_by_user_id, hours_counted
         FROM attendance_sessions
         WHERE term_id = :term_id AND schedule_id = :schedule_id
         LIMIT 1'
    );
    $stmt->execute([':term_id' => $termId, ':schedule_id' => $scheduleId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'term_id' => (int)$row['term_id'],
        'schedule_id' => (int)$row['schedule_id'],
        'doctor_id' => (int)$row['doctor_id'],
        'opened_at' => (string)$row['opened_at'],
        'opened_by_user_id' => (int)$row['opened_by_user_id'],
        'hours_counted' => (int)$row['hours_counted'],
    ];
}

function dmportal_record_teacher_attendance_session(
    PDO $pdo,
    int $termId,
    int $scheduleId,
    int $doctorId,
    int $userId
): void {
    $now = dmportal_cairo_now()->format('Y-m-d H:i:s');
    $sql = 'INSERT INTO attendance_sessions (term_id, schedule_id, doctor_id, opened_at, opened_by_user_id, hours_counted)
            VALUES (:term_id, :schedule_id, :doctor_id, :opened_at, :user_id, 1)
            ON DUPLICATE KEY UPDATE
              opened_at = IF(hours_counted = 0, VALUES(opened_at), opened_at),
              opened_by_user_id = IF(hours_counted = 0, VALUES(opened_by_user_id), opened_by_user_id),
              hours_counted = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':term_id' => $termId,
        ':schedule_id' => $scheduleId,
        ':doctor_id' => $doctorId,
        ':opened_at' => $now,
        ':user_id' => $userId,
    ]);
}

/**
 * @return array{
 *   lecture_window_state:string,
 *   attendance_locked:bool,
 *   can_take_attendance:bool,
 *   can_open_attendance:bool,
 *   hours_counted:bool,
 *   attendance_taken:bool
 * }
 */
function dmportal_attendance_access_flags(
    string $role,
    string $lectureWindowState,
    ?array $session
): array {
    $isTeacher = $role === 'teacher';
    $sessionExists = $session !== null;
    $hoursCounted = $sessionExists && (int)($session['hours_counted'] ?? 0) === 1;

    $attendanceLocked = false;
    $canTakeAttendance = false;
    $canOpenAttendance = true;

    if ($isTeacher) {
        $attendanceLocked = $lectureWindowState === 'ended';
        $canTakeAttendance = $lectureWindowState === 'active';
        $canOpenAttendance = $lectureWindowState !== 'upcoming' && ($lectureWindowState === 'active' || $sessionExists);
    }

    return [
        'lecture_window_state' => $lectureWindowState,
        'attendance_locked' => $attendanceLocked,
        'can_take_attendance' => $canTakeAttendance,
        'can_open_attendance' => $canOpenAttendance,
        'hours_counted' => $hoursCounted,
        'attendance_taken' => $sessionExists,
    ];
}

/**
 * @return array<string,mixed>
 */
function dmportal_attendance_meta_for_schedule(PDO $pdo, int $scheduleId, string $role): array
{
    $range = dmportal_schedule_lecture_range($pdo, $scheduleId);
    $nowCairo = dmportal_cairo_now();
    $windowState = 'ended';
    if ($range) {
        $windowState = dmportal_lecture_window_state($nowCairo, $range['start'], $range['end']);
    }

    $termId = 0;
    $termStmt = $pdo->prepare(
        'SELECT w.term_id
         FROM doctor_schedules s
         JOIN weeks w ON w.week_id = s.week_id
         WHERE s.schedule_id = :sid
         LIMIT 1'
    );
    $termStmt->execute([':sid' => $scheduleId]);
    $termRow = $termStmt->fetch();
    if ($termRow) {
        $termId = (int)$termRow['term_id'];
    }

    $session = $termId > 0 ? dmportal_get_attendance_session($pdo, $termId, $scheduleId) : null;

    return dmportal_attendance_access_flags($role, $windowState, $session);
}
