<?php

declare(strict_types=1);

function dmportal_ensure_course_doctor_hours_table(PDO $pdo): void {
    // IMPORTANT: call this BEFORE starting a transaction (DDL causes implicit commit in MySQL)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS course_doctor_hours (\n"
        ."  course_id BIGINT UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
        ."  allocated_hours DECIMAL(6,2) NOT NULL DEFAULT 0,\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (course_id, doctor_id)\n"
        .") ENGINE=InnoDB"
    );
}
