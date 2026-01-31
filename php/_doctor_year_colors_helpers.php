<?php

declare(strict_types=1);

/**
 * Per-year doctor colors.
 *
 * This is optional/backward-compatible: if the table doesn't exist or a specific
 * (doctor_id, year_level) row isn't set, the system falls back to doctors.color_code.
 */
function dmportal_ensure_doctor_year_colors_table(PDO $pdo): void {
    // IMPORTANT: call this BEFORE starting a transaction (DDL causes implicit commit in MySQL)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS doctor_year_colors (\n"
        ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
        ."  year_level TINYINT UNSIGNED NOT NULL COMMENT '1-3',\n"
        ."  color_code CHAR(7) NOT NULL DEFAULT '#0055A4',\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (doctor_id, year_level)\n"
        .") ENGINE=InnoDB"
    );
}

/**
 * Ensure a doctor_schedules.extra_minutes column exists (0..45).
 */
function dmportal_ensure_schedule_extra_minutes_column(PDO $pdo): void {
    // IMPORTANT: call this BEFORE starting a transaction (DDL causes implicit commit in MySQL)
    try {
        $pdo->exec("ALTER TABLE doctor_schedules ADD COLUMN extra_minutes TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER counts_towards_hours");
    } catch (PDOException $e) {
        // MySQL: 1060 = duplicate column name
        if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
            throw $e;
        }
    }
}
