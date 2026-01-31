<?php

declare(strict_types=1);

function dmportal_ensure_doctor_availability_table(PDO $pdo): void
{
    // IMPORTANT: call before transactions (DDL commits).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS doctor_availability (\n"
        ."  availability_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  week_id BIGINT UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
        ."  day_of_week VARCHAR(3) NOT NULL,\n"
        ."  slot_number TINYINT UNSIGNED NOT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (availability_id),\n"
        ."  UNIQUE KEY uq_doctor_availability (week_id, doctor_id, day_of_week, slot_number),\n"
        ."  KEY idx_doctor_availability_week (week_id),\n"
        ."  KEY idx_doctor_availability_doctor (doctor_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Foreign keys optional.
    try {
        $pdo->exec(
            "ALTER TABLE doctor_availability\n"
            ."  ADD CONSTRAINT fk_availability_week FOREIGN KEY (week_id) REFERENCES weeks(week_id) ON DELETE CASCADE ON UPDATE CASCADE,\n"
            ."  ADD CONSTRAINT fk_availability_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE ON UPDATE CASCADE"
        );
    } catch (PDOException $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if (!in_array($code, [1215, 1826], true)) {
            // ignore FK failures
        }
    }
}
