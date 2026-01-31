<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';

auth_require_login(true);

// Save attendance for (schedule_id, student_id).
// - Admin/Management: can save for any schedule.
// - Teacher: can only save for schedules belonging to their doctor_id.

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
    $studentId = (int)($_POST['student_id'] ?? 0);
    $status = strtoupper(trim((string)($_POST['status'] ?? '')));

    if ($scheduleId <= 0) bad_request('schedule_id is required.');
    if ($studentId <= 0) bad_request('student_id is required.');
    if (!in_array($status, ['PRESENT', 'ABSENT'], true)) bad_request('status must be PRESENT or ABSENT.');

    $pdo = get_pdo();

    // Backward compatible: ensure the attendance_records table matches the schedule-based schema.
    dmportal_ensure_attendance_records_table($pdo);

    // Validate schedule exists and enforce teacher ownership
    $stmt = $pdo->prepare('SELECT schedule_id, doctor_id FROM doctor_schedules WHERE schedule_id = :sid LIMIT 1');
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

    // Validate student exists
    $chk = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
    $chk->execute([':id' => $studentId]);
    if (!$chk->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'student_id not found.']);
        exit;
    }

    // Upsert
    $sql = "INSERT INTO attendance_records (schedule_id, student_id, status)
            VALUES (:schedule_id, :student_id, :status)
            ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP";
    $ins = $pdo->prepare($sql);
    $ins->execute([
        ':schedule_id' => $scheduleId,
        ':student_id' => $studentId,
        ':status' => $status,
    ]);

    echo json_encode(['success' => true, 'data' => ['saved' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to set attendance.']);
}
