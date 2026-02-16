<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

// Returns students for a given schedule_id + their attendance status.
// - Admin/Management: can access any schedule.
// - Teacher: can only access schedules that belong to their doctor_id.

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $scheduleId = (int)($_GET['schedule_id'] ?? 0);
    if ($scheduleId <= 0) bad_request('schedule_id is required.');

    $pdo = get_pdo();

    // Backward compatible: ensure the attendance_records table matches the schedule-based schema.
    dmportal_ensure_attendance_records_table($pdo);

    // Load schedule meta (and enforce ownership if teacher)
    $stmt = $pdo->prepare(
        'SELECT s.schedule_id, s.week_id, s.doctor_id, s.day_of_week, s.slot_number, s.room_code,
                c.course_id, c.course_name, c.year_level,
                d.full_name AS doctor_name
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         WHERE s.schedule_id = :sid
         LIMIT 1'
    );
    $stmt->execute([':sid' => $scheduleId]);
    $sched = $stmt->fetch();
    if (!$sched) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule slot not found.']);
        exit;
    }

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');

    if ($role === 'teacher') {
        $ownId = (int)($u['doctor_id'] ?? 0);
        if ($ownId <= 0 || (int)$sched['doctor_id'] !== $ownId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }
    }

    $yearLevel = (int)$sched['year_level'];

    // Student list for that year (program not filtered; this portal is Digital Marketing)
    $studentsStmt = $pdo->prepare(
        'SELECT student_id, full_name, email, student_code
         FROM students
         WHERE year_level = :y
         ORDER BY full_name ASC'
    );
    $studentsStmt->execute([':y' => $yearLevel]);
    $students = $studentsStmt->fetchAll();

    // Attendance status for schedule
    $termId = dmportal_get_term_id_for_week($pdo, (int)$sched['week_id']);

    $attStmt = $pdo->prepare(
        'SELECT student_id, status
         FROM attendance_records
         WHERE schedule_id = :sid AND term_id = :term_id'
    );
    $attStmt->execute([':sid' => $scheduleId, ':term_id' => $termId]);
    $map = [];
    foreach ($attStmt->fetchAll() as $r) {
        $map[(string)$r['student_id']] = (string)$r['status'];
    }

    // Merge
    $items = [];
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $items[] = [
            'student_id' => $sid,
            'full_name' => (string)$s['full_name'],
            'email' => (string)($s['email'] ?? ''),
            'student_code' => (string)($s['student_code'] ?? ''),
            'attendance_status' => $map[(string)$sid] ?? null,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'schedule' => [
                'schedule_id' => (int)$sched['schedule_id'],
                'week_id' => (int)$sched['week_id'],
                'term_id' => $termId,
                'day_of_week' => (string)$sched['day_of_week'],
                'slot_number' => (int)$sched['slot_number'],
                'room_code' => (string)($sched['room_code'] ?? ''),
                'course_id' => (int)$sched['course_id'],
                'course_name' => (string)$sched['course_name'],
                'year_level' => (int)$sched['year_level'],
                'doctor_id' => (int)$sched['doctor_id'],
                'doctor_name' => (string)($sched['doctor_name'] ?? ''),
            ],
            'items' => $items,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance.']);
}
