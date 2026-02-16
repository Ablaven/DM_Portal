<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

// Returns scheduled slots for Attendance grid.
// - Admin/Management: returns all schedules for selected week/year.
// - Teacher: returns only schedules belonging to their own doctor_id.

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $weekId = (int)($_GET['week_id'] ?? 0);
    $yearLevel = (int)($_GET['year_level'] ?? 0);

    if ($yearLevel < 1 || $yearLevel > 3) {
        bad_request('year_level must be 1-3.');
    }

    $pdo = get_pdo();

    // Backward compatible: ensure the attendance_records table matches the schedule-based schema.
    dmportal_ensure_attendance_records_table($pdo);

    // Ensure optional per-year doctor color table exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    if ($weekId <= 0) {
        $stmt = $pdo->prepare("SELECT week_id FROM weeks WHERE status='active' AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
        $stmt->execute([':term_id' => $termId]);
        $wk = $stmt->fetch();
        if (!$wk) {
            echo json_encode(['success' => true, 'data' => ['week_id' => null, 'year_level' => $yearLevel, 'grid' => [], 'term_id' => $termId]]);
            exit;
        }
        $weekId = (int)$wk['week_id'];
    }

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');

    // In normal mode, teachers are restricted to their own doctor_id.
    // In override mode (allowed_pages explicitly set), we still enforce ownership for attendance.
    // (This endpoint protects student attendance data.)
    $doctorFilterId = null;
    if ($role === 'teacher') {
        $did = (int)($u['doctor_id'] ?? 0);
        if ($did <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account is missing doctor_id.']);
            exit;
        }
        $doctorFilterId = $did;
    }

    $sql =
        "SELECT s.schedule_id, s.day_of_week, s.slot_number, s.room_code,
                d.doctor_id, d.full_name AS doctor_name,
                c.course_id, c.course_name, c.course_type, c.subject_code, c.year_level,
                COALESCE(dyc.color_code, d.color_code) AS doctor_color
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         LEFT JOIN doctor_year_colors dyc
           ON dyc.doctor_id = s.doctor_id AND dyc.year_level = c.year_level
         WHERE s.week_id = :week_id
           AND c.year_level = :year_level";

    $params = [':week_id' => $weekId, ':year_level' => $yearLevel];

    if ($doctorFilterId !== null) {
        $sql .= ' AND s.doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctorFilterId;
    }

    $sql .= ' ORDER BY s.day_of_week ASC, s.slot_number ASC, s.schedule_id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // grid[day][slot] = {multiple:boolean, items:[...]}
    $grid = [];
    foreach ($rows as $r) {
        $day = (string)$r['day_of_week'];
        $slot = (int)$r['slot_number'];
        if (!isset($grid[$day])) $grid[$day] = [];
        $key = (string)$slot;
        if (!isset($grid[$day][$key])) {
            $grid[$day][$key] = ['multiple' => false, 'items' => []];
        }

        $grid[$day][$key]['items'][] = [
            'schedule_id' => (int)$r['schedule_id'],
            'course_id' => (int)$r['course_id'],
            'course_name' => (string)$r['course_name'],
            'course_type' => (string)$r['course_type'],
            'subject_code' => (string)($r['subject_code'] ?? ''),
            'year_level' => (int)$r['year_level'],
            'room_code' => (string)($r['room_code'] ?? ''),
            'doctor_id' => (int)$r['doctor_id'],
            'doctor_name' => (string)($r['doctor_name'] ?? ''),
            'doctor_color' => (string)($r['doctor_color'] ?? ''),
        ];

        if (count($grid[$day][$key]['items']) > 1) {
            $grid[$day][$key]['multiple'] = true;
        }
    }

    echo json_encode(['success' => true, 'data' => ['week_id' => $weekId, 'year_level' => $yearLevel, 'grid' => $grid, 'term_id' => $termId]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance grid.']);
}
