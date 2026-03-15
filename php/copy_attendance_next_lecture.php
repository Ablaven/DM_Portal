<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

auth_require_roles(['admin', 'management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    if ($scheduleId <= 0) {
        bad_request('schedule_id is required.');
    }

    $pdo = get_pdo();

    dmportal_ensure_attendance_records_table($pdo);

    $schedStmt = $pdo->prepare(
        'SELECT s.schedule_id, s.week_id, s.day_of_week, s.slot_number, s.course_id,
                c.course_name, c.subject_code, c.year_level
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         WHERE s.schedule_id = :sid
         LIMIT 1'
    );
    $schedStmt->execute([':sid' => $scheduleId]);
    $current = $schedStmt->fetch();
    $termId = $current ? dmportal_get_term_id_for_week($pdo, (int)$current['week_id']) : 0;
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule slot not found.']);
        exit;
    }

    $nextStmt = $pdo->prepare(
        "SELECT s.schedule_id, s.week_id, s.day_of_week, s.slot_number
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         WHERE s.week_id = :week_id
           AND s.day_of_week = :day_of_week
           AND s.slot_number > :slot_number
           AND c.year_level = :year_level
         ORDER BY s.slot_number ASC, s.schedule_id ASC
         LIMIT 1"
    );
    $nextStmt->execute([
        ':week_id' => (int)$current['week_id'],
        ':day_of_week' => (string)$current['day_of_week'],
        ':slot_number' => (int)$current['slot_number'],
        ':year_level' => (int)$current['year_level'],
    ]);
    $next = $nextStmt->fetch();
    if (!$next) {
        echo json_encode([
            'success' => false,
            'error' => 'No next slot found on this day for this year.'
        ]);
        exit;
    }

    $attStmt = $pdo->prepare('SELECT student_id, status FROM attendance_records WHERE schedule_id = :sid AND term_id = :term_id');
    $attStmt->execute([':sid' => $scheduleId, ':term_id' => $termId]);
    $attendance = $attStmt->fetchAll();

    if (!$attendance) {
        echo json_encode([
            'success' => false,
            'error' => 'No attendance records found to copy.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $upsert = $pdo->prepare(
        "INSERT INTO attendance_records (term_id, schedule_id, student_id, status)
         VALUES (:term_id, :schedule_id, :student_id, :status)
         ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
    );

    foreach ($attendance as $row) {
        $upsert->execute([
            ':term_id' => $termId,
            ':schedule_id' => (int)$next['schedule_id'],
            ':student_id' => (int)$row['student_id'],
            ':status' => strtoupper((string)($row['status'] ?? 'ABSENT')) === 'PRESENT' ? 'PRESENT' : 'ABSENT',
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'source_schedule_id' => (int)$scheduleId,
            'target_schedule_id' => (int)$next['schedule_id'],
            'target_week_id' => (int)$next['week_id'],
            'term_id' => $termId,
            'target_day_of_week' => (string)$next['day_of_week'],
            'target_slot_number' => (int)$next['slot_number'],
            'copied' => count($attendance),
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo?->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to copy attendance.']);
}
