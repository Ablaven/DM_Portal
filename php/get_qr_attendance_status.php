<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);
auth_require_roles(['teacher'], true);

// Get list of students who have submitted attendance via QR code
// Returns students marked as PRESENT for this schedule

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $scheduleId = (int)($_GET['schedule_id'] ?? 0);
    
    if ($scheduleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'schedule_id is required.']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get schedule info
    $stmt = $pdo->prepare('SELECT schedule_id, doctor_id FROM doctor_schedules WHERE schedule_id = :sid LIMIT 1');
    $stmt->execute([':sid' => $scheduleId]);
    $sched = $stmt->fetch();
    
    if (!$sched) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule not found.']);
        exit;
    }
    
    // Verify teacher owns this slot
    $u = auth_current_user();
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0 || (int)$sched['doctor_id'] !== $ownId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }
    
    // Get students who have been marked present
    $studentsStmt = $pdo->prepare('
        SELECT 
            s.student_id,
            s.student_code,
            s.full_name,
            ar.status,
            ar.updated_at as marked_at
        FROM attendance_records ar
        JOIN students s ON s.student_id = ar.student_id
        WHERE ar.schedule_id = :sid
        ORDER BY ar.updated_at DESC
    ');
    $studentsStmt->execute([':sid' => $scheduleId]);
    $students = $studentsStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'students' => $students,
            'count' => count($students)
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error in get_qr_attendance_status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to get attendance status.']);
}
