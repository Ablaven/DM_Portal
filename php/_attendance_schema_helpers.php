<?php

declare(strict_types=1);

/**
 * Attendance table (schedule-based, year-only) schema helper.
 *
 * Backward compatible with older DB dumps that had an old attendance_records schema.
 * If an incompatible attendance_records table exists, it will be renamed to
 * attendance_records_legacy_YYYYMMDD_HHMMSS and a fresh schedule-based table created.
 */
function dmportal_ensure_attendance_records_table(PDO $pdo): void
{
    // IMPORTANT: call this BEFORE starting a transaction (DDL causes implicit commit in MySQL)

    // 1) Table exists?
    $existsStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance_records'"
    );
    $existsStmt->execute();
    $exists = (int)$existsStmt->fetchColumn() > 0;

    if (!$exists) {
        dmportal_create_attendance_records_table($pdo);
        return;
    }

    // 2) Does it have schedule_id and term_id?
    $colStmt = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance_records'"
    );
    $colStmt->execute();
    $columns = array_map('strtolower', array_column($colStmt->fetchAll(), 'COLUMN_NAME'));
    $hasScheduleId = in_array('schedule_id', $columns, true);
    $hasTermId = in_array('term_id', $columns, true);

    if (!$hasScheduleId) {
        // Incompatible old schema. Rename it out of the way (keep data for manual inspection).
        $suffix = gmdate('Ymd_His');
        $legacy = "attendance_records_legacy_{$suffix}";
        $pdo->exec("RENAME TABLE attendance_records TO {$legacy}");
        dmportal_create_attendance_records_table($pdo);
        return;
    }

    if (!$hasTermId) {
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1");
    }

    // 3) Ensure unique key is (term_id, schedule_id, student_id).
    //    Old installs may have the wrong unique key (schedule_id, student_id) which caused
    //    attendance saved in a later term to silently overwrite an earlier term's record via
    //    ON DUPLICATE KEY UPDATE, making saves appear to vanish when re-opening a slot.
    //    Drop the old key if it exists, then add the correct one.

    // Drop the old narrow unique key if present (ignore errors if it doesn't exist).
    try {
        $pdo->exec("ALTER TABLE attendance_records DROP INDEX uq_attendance_schedule_student");
    } catch (PDOException $e) {
        // 1091 = Can't DROP; key doesn't exist — that's fine.
        $code = (int)($e->errorInfo[1] ?? 0);
        if ($code !== 1091) {
            throw $e;
        }
    }

    // Add the correct unique key that includes term_id.
    try {
        $pdo->exec("ALTER TABLE attendance_records ADD UNIQUE KEY uq_attendance_term_schedule_student (term_id, schedule_id, student_id)");
    } catch (PDOException $e) {
        // 1061 = duplicate key name — already correct, fine.
        $code = (int)($e->errorInfo[1] ?? 0);
        if ($code !== 1061) {
            throw $e;
        }
    }

    // 4) Ensure indexes used by APIs exist
    foreach ([
        "ALTER TABLE attendance_records ADD KEY idx_attendance_term (term_id, schedule_id)",
        "ALTER TABLE attendance_records ADD KEY idx_attendance_student (student_id)",
        "ALTER TABLE attendance_records ADD KEY idx_attendance_schedule (schedule_id)",
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code !== 1061) throw $e;
        }
    }
}

function dmportal_create_attendance_records_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_records (\n"
        ."  attendance_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,\n"
        ."  schedule_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  student_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  status ENUM('PRESENT','ABSENT') NOT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (attendance_id),\n"
        ."  UNIQUE KEY uq_attendance_term_schedule_student (term_id, schedule_id, student_id),\n"
        ."  KEY idx_attendance_term (term_id, schedule_id),\n"
        ."  KEY idx_attendance_student (student_id),\n"
        ."  KEY idx_attendance_schedule (schedule_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Foreign keys are optional here: production dumps may already have them.
    // Add if possible; ignore errors.
    try {
        $pdo->exec(
            "ALTER TABLE attendance_records\n"
            ."  ADD CONSTRAINT fk_attendance_schedule FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(schedule_id) ON DELETE CASCADE ON UPDATE CASCADE,\n"
            ."  ADD CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE"
        );
    } catch (PDOException $e) {
        // 1215 = cannot add foreign key constraint, 1826 = duplicate foreign key constraint name
        $code = (int)($e->errorInfo[1] ?? 0);
        if (!in_array($code, [1215, 1826], true)) {
            // Some environments will throw different codes; ignore any FK-related failure.
        }
    }
}
