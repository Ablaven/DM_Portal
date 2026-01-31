<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

auth_require_roles(['admin','student'], true);

$program = trim((string)($_GET['program'] ?? ''));
$yearLevel = (int)($_GET['year_level'] ?? 0);
$semester = (int)($_GET['semester'] ?? 0);
$weekId = (int)($_GET['week_id'] ?? 0);

if ($program === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'program is required.']);
    exit;
}

if ($yearLevel < 1 || $yearLevel > 3) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'year_level must be 1-3.']);
    exit;
}

if ($semester < 1 || $semester > 2) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'semester must be 1 or 2.']);
    exit;
}

try {
    $pdo = get_pdo();

    // Ensure optional per-year doctor color table exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    if ($weekId <= 0) {
        $wk = $pdo->query("SELECT week_id FROM weeks WHERE status='active' ORDER BY week_id DESC LIMIT 1")->fetch();
        if (!$wk) {
            echo json_encode(['success'=>true,'data'=>['week_id'=>null,'grid'=>[]]]);
            exit;
        }
        $weekId = (int)$wk['week_id'];
    }

    // Find all scheduled lectures for this week where course matches program + year.
    $stmt = $pdo->prepare(
        "SELECT s.day_of_week, s.slot_number,
                c.course_id, c.course_name, c.course_type, c.subject_code,
                d.full_name AS doctor_name,
                COALESCE(dyc.color_code, d.color_code) AS doctor_color
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         LEFT JOIN doctor_year_colors dyc
           ON dyc.doctor_id = s.doctor_id AND dyc.year_level = c.year_level
         LEFT JOIN doctor_week_cancellations x
           ON x.week_id = s.week_id AND x.doctor_id = s.doctor_id AND x.day_of_week = s.day_of_week
         LEFT JOIN doctor_slot_cancellations xs
           ON xs.week_id = s.week_id AND xs.doctor_id = s.doctor_id AND xs.day_of_week = s.day_of_week AND xs.slot_number = s.slot_number
         WHERE s.week_id = :week_id
           AND x.cancellation_id IS NULL
           AND xs.slot_cancellation_id IS NULL
           AND s.counts_towards_hours = 1
           AND c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :semester"
    );

    $stmt->execute([
        ':week_id' => $weekId,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
    ]);

    $rows = $stmt->fetchAll();

    // Combine across all doctors.
    // If multiple entries exist for same day/slot -> mark as Multiple.
    $grid = [];
    foreach ($rows as $r) {
        $day = $r['day_of_week'];
        $slot = (int)$r['slot_number'];

        if (!isset($grid[$day])) $grid[$day] = [];

        $key = (string)$slot;
        if (!isset($grid[$day][$key])) {
            $grid[$day][$key] = [
                'kind' => 'single',
                'course_id' => (int)$r['course_id'],
                'course_name' => $r['course_name'],
                'course_type' => $r['course_type'],
                'subject_code' => $r['subject_code'],
                'doctor_name' => $r['doctor_name'],
                'doctor_color' => $r['doctor_color'],

            ];
        } else {
            $grid[$day][$key] = [
                'kind' => 'multiple',
                'course_name' => 'Multiple',
                'course_type' => 'R',
                'doctor_color' => '#999999',
            ];
        }
    }

    // Also return cancellations grouped by doctor (day + slot)
    $cStmt = $pdo->prepare(
        "SELECT doctor_id, day_of_week, reason
         FROM doctor_week_cancellations
         WHERE week_id = :week_id"
    );
    $cStmt->execute([':week_id' => $weekId]);
    $cRows = $cStmt->fetchAll();
    $cancellations = [];
    foreach ($cRows as $cr) {
        $did = (string)$cr['doctor_id'];
        if (!isset($cancellations[$did])) $cancellations[$did] = [];
        $cancellations[$did][$cr['day_of_week']] = $cr['reason'];
    }

    // Slot cancellations
    $scStmt = $pdo->prepare(
        "SELECT doctor_id, day_of_week, slot_number, reason
         FROM doctor_slot_cancellations
         WHERE week_id = :week_id"
    );
    $scStmt->execute([':week_id' => $weekId]);
    $scRows = $scStmt->fetchAll();
    $slotCancellations = [];
    foreach ($scRows as $sr) {
        $did = (string)$sr['doctor_id'];
        if (!isset($slotCancellations[$did])) $slotCancellations[$did] = [];
        $day = $sr['day_of_week'];
        $slot = (int)$sr['slot_number'];
        if (!isset($slotCancellations[$did][$day])) $slotCancellations[$did][$day] = [];
        $slotCancellations[$did][$day][(string)$slot] = $sr['reason'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => $weekId,
            'program' => $program,
            'year_level' => $yearLevel,
            'semester' => $semester,
            'grid' => $grid,
            'cancellations' => $cancellations,
            'slot_cancellations' => $slotCancellations,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to fetch student schedule.']);
}
