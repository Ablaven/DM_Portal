<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

// Access control:
// - Admins can read any schedule.
// - Teachers can only read their own schedule (enforced below), even if Allowed Pages is enabled.
$u = auth_current_user();
if (($u['role'] ?? '') !== 'admin' && ($u['role'] ?? '') !== 'management') {
    auth_require_roles(['teacher'], true);
}

$doctorIdRaw = $_GET['doctor_id'] ?? '';
$doctorId = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;

$weekIdRaw = $_GET['week_id'] ?? '';
$weekId = is_numeric($weekIdRaw) ? (int)$weekIdRaw : 0;

// Enforce teacher ownership ALWAYS (even in Allowed Pages override mode).
// Teachers may be granted access to the doctor schedule page, but they must only see their own schedule.
if ((($u['role'] ?? '') === 'teacher')) {
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0 || $doctorId !== $ownId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

if ($doctorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'doctor_id is required.']);
    exit;
}

try {
    $pdo = get_pdo();

    // Ensure optional per-year doctor color table exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    // Verify doctor exists
    $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    // Default to active week if week_id not provided
    if ($weekId <= 0) {
        $stmt = $pdo->prepare("SELECT week_id FROM weeks WHERE status='active' AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
        $stmt->execute([':term_id' => $termId]);
        $wk = $stmt->fetch();
        if (!$wk) {
            // No active week yet
            echo json_encode(['success' => true, 'data' => ['doctor_id' => $doctorId, 'week_id' => null, 'grid' => [], 'term_id' => $termId]]);
            exit;
        }
        $weekId = (int)$wk['week_id'];
    }

    $stmt = $pdo->prepare(
        "SELECT s.day_of_week, s.slot_number, s.room_code, s.counts_towards_hours,
                COALESCE(s.extra_minutes, 0) AS extra_minutes,
                c.course_id, c.course_name, c.course_type, c.subject_code,
                c.program, c.year_level, c.semester,
                COALESCE(dyc.color_code, d.color_code) AS doctor_color,
                NULL AS room_id, NULL AS floor_id
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         LEFT JOIN doctor_year_colors dyc
           ON dyc.doctor_id = s.doctor_id AND dyc.year_level = c.year_level
         WHERE s.doctor_id = :doctor_id AND s.week_id = :week_id"
    );
    $stmt->execute([':doctor_id' => $doctorId, ':week_id' => $weekId]);
    $rows = $stmt->fetchAll();

    // Return as a map for easy grid rendering: grid[day][slot] = course
    $grid = [];
    foreach ($rows as $r) {
        $day = $r['day_of_week'];
        $slot = (int)$r['slot_number'];
        if (!isset($grid[$day])) {
            $grid[$day] = [];
        }
        $grid[$day][(string)$slot] = [
            'course_id' => (int)$r['course_id'],
            'course_name' => $r['course_name'],
            'course_type' => $r['course_type'],
            'subject_code' => $r['subject_code'],
            'program' => $r['program'],
            'year_level' => (int)$r['year_level'],
            'semester' => (int)$r['semester'],
            'room_code' => $r['room_code'],
            'room_id' => $r['room_id'] !== null ? (int)$r['room_id'] : null,
            'floor_id' => $r['floor_id'] !== null ? (int)$r['floor_id'] : null,
            'counts_towards_hours' => (int)($r['counts_towards_hours'] ?? 1),
            'extra_minutes' => (int)($r['extra_minutes'] ?? 0),
            'doctor_color' => $r['doctor_color'],
        ];
    }

    // Load day cancellations for this doctor/week
    $cStmt = $pdo->prepare('SELECT day_of_week, reason FROM doctor_week_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id');
    $cStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
    $cRows = $cStmt->fetchAll();
    $cancellations = [];
    foreach ($cRows as $cr) {
        $cancellations[$cr['day_of_week']] = $cr['reason'];
    }

    // Load slot cancellations for this doctor/week (optional table)
    $slotCancellations = [];
    try {
        $scStmt = $pdo->prepare('SELECT day_of_week, slot_number, reason FROM doctor_slot_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id');
        $scStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
        $scRows = $scStmt->fetchAll();
        foreach ($scRows as $sr) {
            $d = $sr['day_of_week'];
            $s = (int)$sr['slot_number'];
            if (!isset($slotCancellations[$d])) $slotCancellations[$d] = [];
            $slotCancellations[$d][(string)$s] = $sr['reason'];
        }
    } catch (PDOException $e) {
        // MySQL: 1146 = table doesn't exist
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
            throw $e;
        }
        $slotCancellations = [];
    }

    // Doctor unavailability within the week (date/time blocks)
    $wkStmt = $pdo->prepare('SELECT start_date FROM weeks WHERE week_id = :id');
    $wkStmt->execute([':id' => $weekId]);
    $wkRow = $wkStmt->fetch();

    $unavailability = [];
    if ($wkRow && !empty($wkRow['start_date'])) {
        $start = new DateTimeImmutable($wkRow['start_date'] . ' 00:00:00');
        $end = $start->modify('+7 days');

        // doctor_unavailability is optional (older DB imports may not have it)
        try {
            $uStmt = $pdo->prepare(
                'SELECT unavailability_id, start_datetime, end_datetime, reason
                 FROM doctor_unavailability
                 WHERE doctor_id = :doctor_id
                   AND start_datetime < :end_dt
                   AND end_datetime > :start_dt
                 ORDER BY start_datetime ASC'
            );
            $uStmt->execute([
                ':doctor_id' => $doctorId,
                ':start_dt' => $start->format('Y-m-d H:i:s'),
                ':end_dt' => $end->format('Y-m-d H:i:s'),
            ]);
            $unavailability = $uStmt->fetchAll();
        } catch (PDOException $e) {
            // MySQL: 1146 = table doesn't exist
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
            $unavailability = [];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'doctor_id' => $doctorId,
            'week_id' => $weekId,
            'term_id' => $termId,
            'grid' => $grid,
            'cancellations' => $cancellations,
            'slot_cancellations' => $slotCancellations,
            'unavailability' => $unavailability,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch schedule.',
        // 'debug' => $e->getMessage(),
    ]);
}
