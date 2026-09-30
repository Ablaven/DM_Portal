<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_course_hours_helpers.php';
require_once __DIR__ . '/_attendance_session_helpers.php';

auth_require_login(true);
$u = auth_current_user();
if (($u['role'] ?? '') !== 'admin') {
    auth_require_roles(['teacher'], true);
}

$doctorIdRaw = $_GET['doctor_id'] ?? '';

// Teacher can only access their own doctor_id (normal mode only)
if (!auth_is_allowed_pages_override_mode() && (($u['role'] ?? '') === 'teacher')) {
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId > 0) {
        $doctorIdRaw = (string)$ownId;
    }
}
$doctorId = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;

if ($doctorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'doctor_id is required.']);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_attendance_sessions_table($pdo);

    // Resolve the active term so we show only the current semester's courses
    require_once __DIR__ . '/_term_helpers.php';
    $activeTermId = dmportal_get_active_term_id($pdo);

    // Get the active semester number from the term
    $termStmt = $pdo->prepare('SELECT semester FROM terms WHERE term_id = :term_id LIMIT 1');
    $termStmt->execute([':term_id' => $activeTermId]);
    $activeSemester = (int)($termStmt->fetchColumn() ?: 0);

    // Verify doctor exists
    $chk = $pdo->prepare('SELECT doctor_id, full_name FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    $doctor = $chk->fetch();

    if (!$doctor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    $semesterFilter = $activeSemester > 0 ? 'AND c.semester = :semester' : '';

    // Check if course_doctor_hours table exists
    $hasCdh = false;
    try {
        $pdo->query('SELECT 1 FROM course_doctor_hours LIMIT 0');
        $hasCdh = true;
    } catch (Throwable $ignore) {
    }

    $hJoin = $hasCdh
        ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c.course_id AND h.doctor_id = :doctor_id_h'
        : '';
    $allocJoin = $hasCdh
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
        : '';

    // For split courses: use doctor's allocated hours - doctor's scheduled hours
    // For non-split: use total_hours - total scheduled hours for all doctors
    $stmt = $pdo->prepare(
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester, c.course_type, c.subject_code, c.total_hours,
                CASE
                  WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
                  ELSE c.total_hours
                END AS allocated_hours,
                GREATEST(0, ROUND(
                  (
                    CASE
                      WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
                      ELSE c.total_hours
                    END
                  ) - (
                    COALESCE(xdoc.scheduled_base_hours, 0) + COALESCE(xdoc.scheduled_extra_hours, 0)
                  ),
                2)) AS remaining_hours
         FROM courses c
         JOIN course_doctors cd ON cd.course_id = c.course_id AND cd.doctor_id = :doctor_id
         $hJoin
         $allocJoin
         LEFT JOIN (
           " . dmportal_done_hours_course_subquery_sql('s.doctor_id = :doctor_id_done', $activeTermId) . "
         ) xdoc ON xdoc.course_id = c.course_id
         WHERE 1=1 $semesterFilter
         ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC"
    );

    $params = [
        ':doctor_id'      => $doctorId,
        ':doctor_id_done' => $doctorId,
    ];
    if ($hasCdh) {
        $params[':doctor_id_h'] = $doctorId;
    }
    if ($activeSemester > 0) {
        $params[':semester'] = $activeSemester;
    }
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'data' => [
            'doctor' => [
                'doctor_id' => (int)$doctor['doctor_id'],
                'full_name' => $doctor['full_name'],
            ],
            'active_term_id' => $activeTermId,
            'active_semester' => $activeSemester,
            'courses' => $stmt->fetchAll(),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch doctor courses.',
        // 'debug' => $e->getMessage(),
    ]);
}
