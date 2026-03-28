<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin', 'management'], true);

/**
 * Scheduled hours per course (all doctors) — course-level remaining.
 *
 * @param string $courseAlias Table alias for courses (e.g. "c" or "c0")
 * @param string $joinAlias   Alias for the joined aggregate subquery
 */
function dmportal_schedule_hours_join_xall(string $courseAlias, string $joinAlias = 'xall'): string
{
    return '
         LEFT JOIN (
           SELECT s.course_id,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s
           LEFT JOIN doctor_week_cancellations cw
             ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
           LEFT JOIN doctor_slot_cancellations cs
             ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
           WHERE cw.cancellation_id IS NULL
             AND cs.slot_cancellation_id IS NULL
             AND s.counts_towards_hours = 1
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
function dmportal_schedule_hours_join_xdoc(string $courseAlias, string $placeholder, string $joinAlias = 'xdoc'): string
{
    return '
         LEFT JOIN (
           SELECT s.course_id,
                  s.doctor_id,
                  SUM(1.5) AS scheduled_base_hours,
                  SUM(COALESCE(s.extra_minutes,0) / 60) AS scheduled_extra_hours
           FROM doctor_schedules s
           LEFT JOIN doctor_week_cancellations cw
             ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
           LEFT JOIN doctor_slot_cancellations cs
             ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
           WHERE cw.cancellation_id IS NULL
             AND cs.slot_cancellation_id IS NULL
             AND s.counts_towards_hours = 1
           GROUP BY s.course_id, s.doctor_id
         ) ' . $joinAlias . ' ON ' . $joinAlias . '.course_id = ' . $courseAlias . '.course_id AND ' . $joinAlias . '.doctor_id = ' . $placeholder;
}

try {
    $pdo = get_pdo();

    $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

    $hasCdh = false;
    try {
        $pdo->query('SELECT 1 FROM course_doctor_hours LIMIT 1');
        $hasCdh = true;
    } catch (Throwable $e) {
        $hasCdh = false;
    }

    // Joins for per-course remaining subquery (alias c0) — avoids row multiplication from course_doctors before GROUP BY.
    $hJoinC0 = $hasCdh
        ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c0.course_id AND h.doctor_id = :doctor_id_h'
        : 'LEFT JOIN (SELECT NULL AS course_id, NULL AS doctor_id, NULL AS allocated_hours) h ON 1=0';

    $allocJoinC0 = $hasCdh
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c0.course_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

    if ($doctorId <= 0) {
        $stmt = $pdo->query(
            'SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                    c.course_type, c.subject_code, c.total_hours,
                    c.coefficient,
                    c.default_room_code,
                    c.doctor_id,
                    d.full_name AS doctor_name,
                    agg.doctor_ids,
                    agg.doctor_names,
                    GREATEST(0, ROUND(c.total_hours - (COALESCE(xall.scheduled_base_hours,0) + COALESCE(xall.scheduled_extra_hours,0)), 2)) AS remaining_hours
             FROM courses c
             LEFT JOIN doctors d ON d.doctor_id = c.doctor_id
             LEFT JOIN (
               SELECT cd.course_id,
                      GROUP_CONCAT(DISTINCT cd.doctor_id ORDER BY cd.doctor_id SEPARATOR \',\') AS doctor_ids,
                      GROUP_CONCAT(DISTINCT d2.full_name ORDER BY d2.full_name SEPARATOR \', \') AS doctor_names
               FROM course_doctors cd
               LEFT JOIN doctors d2 ON d2.doctor_id = cd.doctor_id
               GROUP BY cd.course_id
             ) agg ON agg.course_id = c.course_id
             ' . dmportal_schedule_hours_join_xall('c', 'xall') . '
             ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC'
        );
        $rows = $stmt->fetchAll();
    } else {
        $remSub = '
            SELECT c0.course_id,
                   CASE
                     WHEN asg.doctor_id IS NOT NULL THEN
                       GREATEST(0, ROUND(
                         (
                           CASE
                             WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
                             ELSE (COALESCE(c0.total_hours, 0) / GREATEST(cd_cnt.cnt, 1))
                           END
                         ) - (
                           COALESCE(xdoc.scheduled_base_hours, 0) + COALESCE(xdoc.scheduled_extra_hours, 0)
                         ),
                       2))
                     ELSE
                       GREATEST(0, ROUND(c0.total_hours - (COALESCE(xall_r.scheduled_base_hours,0) + COALESCE(xall_r.scheduled_extra_hours,0)), 2))
                   END AS remaining_hours
            FROM courses c0
            LEFT JOIN course_doctors asg ON asg.course_id = c0.course_id AND asg.doctor_id = :doctor_id_asg
            LEFT JOIN (
              SELECT course_id, COUNT(*) AS cnt FROM course_doctors GROUP BY course_id
            ) cd_cnt ON cd_cnt.course_id = c0.course_id
            ' . $hJoinC0 . '
            ' . $allocJoinC0 . '
            ' . dmportal_schedule_hours_join_xall('c0', 'xall_r') . '
            ' . dmportal_schedule_hours_join_xdoc('c0', ':doctor_id_xdoc', 'xdoc') . '
        ';

        $stmt = $pdo->prepare(
            'SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
                    c.course_type, c.subject_code, c.total_hours,
                    c.coefficient,
                    c.default_room_code,
                    c.doctor_id,
                    d.full_name AS doctor_name,
                    agg.doctor_ids,
                    agg.doctor_names,
                    rem.remaining_hours
             FROM courses c
             LEFT JOIN doctors d ON d.doctor_id = c.doctor_id
             LEFT JOIN (
               SELECT cd.course_id,
                      GROUP_CONCAT(DISTINCT cd.doctor_id ORDER BY cd.doctor_id SEPARATOR \',\') AS doctor_ids,
                      GROUP_CONCAT(DISTINCT d2.full_name ORDER BY d2.full_name SEPARATOR \', \') AS doctor_names
               FROM course_doctors cd
               LEFT JOIN doctors d2 ON d2.doctor_id = cd.doctor_id
               GROUP BY cd.course_id
             ) agg ON agg.course_id = c.course_id
             LEFT JOIN (
               ' . $remSub . '
             ) rem ON rem.course_id = c.course_id
             ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC'
        );
        $bind = [
            ':doctor_id_asg' => $doctorId,
            ':doctor_id_xdoc' => $doctorId,
        ];
        if ($hasCdh) {
            $bind[':doctor_id_h'] = $doctorId;
        }
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    $msg = 'Failed to fetch courses.';
    if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1054) {
        $msg = 'DB schema is missing default_room_code on courses. Please import the updated Digital_Marketing_Portal.sql.';
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $msg,
    ]);
}
