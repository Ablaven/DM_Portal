<?php

declare(strict_types=1);

function dmportal_ensure_course_doctor_hours_table(PDO $pdo): void
{
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

function dmportal_schedule_cancel_joins_sql(): string
{
    return '
           LEFT JOIN doctor_week_cancellations cw
             ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
           LEFT JOIN doctor_slot_cancellations cs
             ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number';
}

function dmportal_attendance_confirmed_join_sql(int $termId = 0): string
{
    $termFilter = $termId > 0 ? ' AND w_h.term_id = ' . $termId : '';
    return '
           JOIN weeks w_h ON w_h.week_id = s.week_id' . $termFilter . '
           JOIN attendance_sessions ash
             ON ash.term_id = w_h.term_id AND ash.schedule_id = s.schedule_id AND ash.hours_counted = 1';
}

function dmportal_schedule_hours_base_where_sql(): string
{
    return '
             cw.cancellation_id IS NULL
             AND cs.slot_cancellation_id IS NULL
             AND s.counts_towards_hours = 1';
}

/**
 * Scheduled hours per course (all doctors) — course-level remaining.
 *
 * @param string $courseAlias Table alias for courses (e.g. "c" or "c0")
 * @param string $joinAlias   Alias for the joined aggregate subquery
 */
function dmportal_schedule_hours_join_xall(string $courseAlias, string $joinAlias = 'xall', int $termId = 0): string
{
    return '
         LEFT JOIN (
           SELECT s.course_id,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s'
        . dmportal_schedule_cancel_joins_sql()
        . dmportal_attendance_confirmed_join_sql($termId) . '
           WHERE' . dmportal_schedule_hours_base_where_sql() . '
           GROUP BY s.course_id
         ) ' . $joinAlias . ' ON ' . $joinAlias . '.course_id = ' . $courseAlias . '.course_id';
}

/**
 * Scheduled hours per (course_id, doctor_id) for split-hour remaining.
 *
 * @param string $courseAlias  Table alias for courses
 * @param string $placeholder  Bound PDO placeholder for doctor_id (e.g. ":doctor_id_xdoc")
 * @param string $joinAlias    Alias for the joined aggregate subquery
 */
function dmportal_schedule_hours_join_xdoc(string $courseAlias, string $placeholder, string $joinAlias = 'xdoc', int $termId = 0): string
{
    return '
         LEFT JOIN (
           SELECT s.course_id,
                  s.doctor_id,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s'
        . dmportal_schedule_cancel_joins_sql()
        . dmportal_attendance_confirmed_join_sql($termId) . '
           WHERE' . dmportal_schedule_hours_base_where_sql() . '
           GROUP BY s.course_id, s.doctor_id
         ) ' . $joinAlias . ' ON ' . $joinAlias . '.course_id = ' . $courseAlias . '.course_id AND ' . $joinAlias . '.doctor_id = ' . $placeholder;
}

/**
 * Done-hours subquery fragment (doctor_id, course_id aggregates).
 */
function dmportal_done_hours_subquery_sql(string $weekCancelJoin, string $slotCancelJoin, int $termId = 0): string
{
    $termFilter = $termId > 0 ? ' AND w_h.term_id = ' . $termId : '';
    return "
          SELECT
            s.doctor_id,
            s.course_id,
            COUNT(*) AS done_slots,
            SUM(COALESCE(s.extra_minutes,0)) AS done_extra_minutes
          FROM doctor_schedules s
          JOIN weeks w_h ON w_h.week_id = s.week_id$termFilter
          JOIN attendance_sessions ash
            ON ash.term_id = w_h.term_id AND ash.schedule_id = s.schedule_id AND ash.hours_counted = 1
          $weekCancelJoin
          $slotCancelJoin
          WHERE s.counts_towards_hours = 1
            AND cw.cancellation_id IS NULL
            AND cs.slot_cancellation_id IS NULL
          GROUP BY s.doctor_id, s.course_id
        ";
}

/**
 * Inline subquery for per-course done slot counts (used in reports).
 */
function dmportal_done_hours_course_subquery_sql(string $extraWhere = '', int $termId = 0): string
{
    $where = dmportal_schedule_hours_base_where_sql();
    if ($extraWhere !== '') {
        $where .= ' AND ' . $extraWhere;
    }

    return '
           SELECT s.course_id,
                  COUNT(*) AS scheduled_slots,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s'
        . dmportal_schedule_cancel_joins_sql()
        . dmportal_attendance_confirmed_join_sql($termId) . '
           WHERE' . $where . '
           GROUP BY s.course_id';
}
