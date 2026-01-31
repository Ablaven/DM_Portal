<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

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
           SELECT s.course_id,
                  COUNT(*) AS scheduled_slots,
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
         ) x ON x.course_id = c.course_id
         ORDER BY c.program ASC, c.year_level ASC, c.course_name ASC"
    );
    $stmt->execute([':doctor_id' => $doctorId]);

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
