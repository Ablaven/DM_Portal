<?php

declare(strict_types=1);

/**
 * Helpers for cancellation that removes schedule entries but supports undo.
 */

function dmportal_ensure_slot_cancellations_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS doctor_slot_cancellations (\n"
        ."  slot_cancellation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  week_id BIGINT UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
        ."  day_of_week ENUM('Sun','Mon','Tue','Wed','Thu') NOT NULL,\n"
        ."  slot_number TINYINT UNSIGNED NOT NULL COMMENT '1-5',\n"
        ."  reason VARCHAR(255) NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (slot_cancellation_id),\n"
        ."  UNIQUE KEY uq_doctor_week_day_slot (week_id, doctor_id, day_of_week, slot_number)\n"
        .") ENGINE=InnoDB"
    );
}


function dmportal_ensure_cancel_restore_table(PDO $pdo): void {
    // Create a backup table (if missing) that stores removed schedule entries.
    // We avoid FK constraints to keep restoration resilient across older imports.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cancelled_doctor_schedules (\n"
        ."  cancelled_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  week_id BIGINT UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
        ."  day_of_week ENUM('Sun','Mon','Tue','Wed','Thu') NOT NULL,\n"
        ."  slot_number TINYINT UNSIGNED NOT NULL,\n"
        ."  course_id BIGINT UNSIGNED NOT NULL,\n"
        ."  room_code VARCHAR(50) NOT NULL,\n"
        ."  counts_towards_hours TINYINT(1) NOT NULL DEFAULT 1,\n"
        ."  cancelled_scope ENUM('day','slot') NOT NULL,\n"
        ."  cancelled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (cancelled_id),\n"
        ."  UNIQUE KEY uq_cancelled_week_doctor_day_slot (week_id, doctor_id, day_of_week, slot_number)\n"
        .") ENGINE=InnoDB"
    );
}

/**
 * Move scheduled entries from doctor_schedules into cancelled_doctor_schedules, then delete them.
 *
 * @param array{week_id:int, doctor_id:int, day:string, slot?:int, scope:'day'|'slot'} $p
 */
function dmportal_cancel_and_remove_slots(PDO $pdo, array $p): int {
    // IMPORTANT: caller must ensure table exists BEFORE starting a transaction.

    $weekId = (int)$p['week_id'];
    $doctorId = (int)$p['doctor_id'];
    $day = (string)$p['day'];
    $scope = (string)$p['scope'];
    $slot = isset($p['slot']) ? (int)$p['slot'] : null;

    if ($scope === 'slot' && ($slot === null || $slot < 1 || $slot > 5)) {
        throw new InvalidArgumentException('slot is required for slot-scope cancellation');
    }

    // Fetch affected schedules
    if ($scope === 'day') {
        $sel = $pdo->prepare(
            'SELECT schedule_id, week_id, doctor_id, day_of_week, slot_number, course_id, room_code, counts_towards_hours '
            .'FROM doctor_schedules '
            .'WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day'
        );
        $sel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
    } else {
        $sel = $pdo->prepare(
            'SELECT schedule_id, week_id, doctor_id, day_of_week, slot_number, course_id, room_code, counts_towards_hours '
            .'FROM doctor_schedules '
            .'WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot'
        );
        $sel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
    }

    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return 0;

    // Insert into backup (upsert)
    $ins = $pdo->prepare(
        'INSERT INTO cancelled_doctor_schedules '
        .'(week_id, doctor_id, day_of_week, slot_number, course_id, room_code, counts_towards_hours, cancelled_scope) '
        .'VALUES (:week_id, :doctor_id, :day, :slot, :course_id, :room_code, :cth, :scope) '
        .'ON DUPLICATE KEY UPDATE '
        .'course_id = VALUES(course_id), room_code = VALUES(room_code), counts_towards_hours = VALUES(counts_towards_hours), cancelled_scope = VALUES(cancelled_scope)'
    );

    foreach ($rows as $r) {
        $ins->execute([
            ':week_id' => (int)$r['week_id'],
            ':doctor_id' => (int)$r['doctor_id'],
            ':day' => (string)$r['day_of_week'],
            ':slot' => (int)$r['slot_number'],
            ':course_id' => (int)$r['course_id'],
            ':room_code' => (string)$r['room_code'],
            ':cth' => (int)($r['counts_towards_hours'] ?? 1),
            ':scope' => $scope,
        ]);
    }

    // Delete from live schedules
    if ($scope === 'day') {
        $del = $pdo->prepare('DELETE FROM doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day');
        $del->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
        return $del->rowCount();
    }

    $del = $pdo->prepare('DELETE FROM doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
    $del->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
    return $del->rowCount();
}

/**
 * Restore schedule entries from cancelled_doctor_schedules back into doctor_schedules.
 *
 * @param array{week_id:int, doctor_id:int, day:string, slot?:int, scope:'day'|'slot'} $p
 */
function dmportal_restore_cancelled_slots(PDO $pdo, array $p): int {
    // IMPORTANT: caller must ensure table exists BEFORE starting a transaction.

    $weekId = (int)$p['week_id'];
    $doctorId = (int)$p['doctor_id'];
    $day = (string)$p['day'];
    $scope = (string)$p['scope'];
    $slot = isset($p['slot']) ? (int)$p['slot'] : null;

    if ($scope === 'slot' && ($slot === null || $slot < 1 || $slot > 5)) {
        throw new InvalidArgumentException('slot is required for slot-scope restore');
    }

    // Get rows to restore
    if ($scope === 'day') {
        $sel = $pdo->prepare(
            'SELECT week_id, doctor_id, day_of_week, slot_number, course_id, room_code, counts_towards_hours '
            .'FROM cancelled_doctor_schedules '
            .'WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day'
        );
        $sel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
    } else {
        $sel = $pdo->prepare(
            'SELECT week_id, doctor_id, day_of_week, slot_number, course_id, room_code, counts_towards_hours '
            .'FROM cancelled_doctor_schedules '
            .'WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot'
        );
        $sel->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
    }

    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return 0;

    // Restore only into empty slots; if slot already exists, keep it (shouldn't happen due to cancellation blocking).
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO doctor_schedules (week_id, doctor_id, course_id, day_of_week, slot_number, room_code, counts_towards_hours) '
        .'VALUES (:week_id, :doctor_id, :course_id, :day, :slot, :room_code, :cth)'
    );

    $restored = 0;
    foreach ($rows as $r) {
        $ins->execute([
            ':week_id' => (int)$r['week_id'],
            ':doctor_id' => (int)$r['doctor_id'],
            ':course_id' => (int)$r['course_id'],
            ':day' => (string)$r['day_of_week'],
            ':slot' => (int)$r['slot_number'],
            ':room_code' => (string)$r['room_code'],
            ':cth' => (int)($r['counts_towards_hours'] ?? 1),
        ]);
        $restored += $ins->rowCount();
    }

    // Remove from backup
    if ($scope === 'day') {
        $del = $pdo->prepare('DELETE FROM cancelled_doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day');
        $del->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day]);
    } else {
        $del = $pdo->prepare('DELETE FROM cancelled_doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
        $del->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
    }

    return $restored;
}
