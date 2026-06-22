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

    // Verify doctor exists
    $chk = $pdo->prepare('SELECT doctor_id, full_name FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    $doctor = $chk->fetch();

    if (!$doctor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT c.course_id, c.course_name, c.program, c.year_level, c.semester, c.course_type, c.subject_code, c.total_hours,
                GREATEST(0, ROUND(c.total_hours - (COALESCE(x.scheduled_base_hours,0) + COALESCE(x.scheduled_extra_hours,0)), 2)) AS remaining_hours
         FROM courses c
         JOIN course_doctors cd ON cd.course_id = c.course_id AND cd.doctor_id = :doctor_id
         LEFT JOIN (
           " . dmportal_done_hours_course_subquery_sql('s.doctor_id = :doctor_id_done') . "
         ) x ON x.course_id = c.course_id
         ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC"
    );
    $stmt->execute([
        ':doctor_id' => $doctorId,
        ':doctor_id_done' => $doctorId,
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'doctor' => [
                'doctor_id' => (int)$doctor['doctor_id'],
                'full_name' => $doctor['full_name'],
            ],
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
