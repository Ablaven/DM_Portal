<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_course_hours_helpers.php';
require_once __DIR__ . '/_attendance_session_helpers.php';

auth_require_roles(['admin', 'management'], true);

try {
    $pdo = get_pdo();
    dmportal_ensure_attendance_sessions_table($pdo);

    // Scope done hours to the active term so a new academic year starts at 0.
    require_once __DIR__ . '/_term_helpers.php';
    $activeTermId = dmportal_get_active_term_id($pdo);

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
        $allSql = 'SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester,
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
             ' . dmportal_schedule_hours_join_xall('c', 'xall', $activeTermId) . '
             ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC';
        $stmt = $pdo->prepare($allSql);
        $stmt->execute([]);
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
            ' . dmportal_schedule_hours_join_xall('c0', 'xall_r', $activeTermId) . '
            ' . dmportal_schedule_hours_join_xdoc('c0', ':doctor_id_xdoc', 'xdoc', $activeTermId) . '
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
