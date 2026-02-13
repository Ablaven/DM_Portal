<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';

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
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule slot not found.']);
        exit;
    }

    $dayMap = ['Sun' => 1, 'Mon' => 2, 'Tue' => 3, 'Wed' => 4, 'Thu' => 5];
    $currentDay = (string)($current['day_of_week'] ?? '');
    $currentDayIndex = $dayMap[$currentDay] ?? 0;
    $currentKey = ((int)$current['week_id'] * 100) + ($currentDayIndex * 10) + (int)$current['slot_number'];

    $nextStmt = $pdo->prepare(
        "SELECT s.schedule_id, s.week_id, s.day_of_week, s.slot_number,
                (s.week_id * 100 + FIELD(s.day_of_week, 'Sun','Mon','Tue','Wed','Thu') * 10 + s.slot_number) AS sort_key
         FROM doctor_schedules s
         WHERE s.course_id = :course_id
           AND (s.week_id * 100 + FIELD(s.day_of_week, 'Sun','Mon','Tue','Wed','Thu') * 10 + s.slot_number) > :current_key
         ORDER BY sort_key ASC, s.schedule_id ASC
         LIMIT 1"
    );
    $nextStmt->execute([
        ':course_id' => (int)$current['course_id'],
        ':current_key' => $currentKey,
    ]);
    $next = $nextStmt->fetch();
    if (!$next) {
        echo json_encode([
            'success' => false,
            'error' => 'No next lecture found for this course.'
        ]);
        exit;
    }

    $attStmt = $pdo->prepare('SELECT student_id, status FROM attendance_records WHERE schedule_id = :sid');
    $attStmt->execute([':sid' => $scheduleId]);
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
        "INSERT INTO attendance_records (schedule_id, student_id, status)
         VALUES (:schedule_id, :student_id, :status)
         ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
    );

    foreach ($attendance as $row) {
        $upsert->execute([
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
