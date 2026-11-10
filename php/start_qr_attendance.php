<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_session_helpers.php';

auth_require_login(true);
auth_require_roles(['teacher'], true);

// Start QR attendance session for a schedule slot
// Only allows starting if:
// 1. Session doesn't already exist for this slot
// 2. Slot belongs to current teacher
// 3. Time window is active

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    
    if ($scheduleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'schedule_id is required.']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get schedule info
    $stmt = $pdo->prepare('SELECT schedule_id, doctor_id, week_id FROM doctor_schedules WHERE schedule_id = :sid LIMIT 1');
    $stmt->execute([':sid' => $scheduleId]);
    $sched = $stmt->fetch();
    
    if (!$sched) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule slot not found.']);
        exit;
    }
    
    // Verify teacher owns this slot
    $u = auth_current_user();
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0 || (int)$sched['doctor_id'] !== $ownId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only start QR attendance for your own lectures.']);
        exit;
    }
    
    // Check if time window is active
    $lectureRange = dmportal_schedule_lecture_range($pdo, $scheduleId);
    if (!$lectureRange) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Could not determine lecture time range.']);
        exit;
    }
    
    $windowState = dmportal_lecture_window_state(
        dmportal_cairo_now(),
        $lectureRange['start'],
        $lectureRange['end'],
        $pdo
    );
    
    if ($windowState !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'QR attendance can only be started during the active lecture window.']);
        exit;
    }
    
    // Check if QR session already exists
    $checkStmt = $pdo->prepare('SELECT session_id, is_closed, token, expires_at FROM attendance_qr_sessions WHERE schedule_id = :sid LIMIT 1');
    $checkStmt->execute([':sid' => $scheduleId]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        if ((int)$existing['is_closed'] === 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'QR session was already used for this slot and has been closed.', 'already_closed' => true]);
            exit;
        }
        
        // Session exists and is open - return it with current token
        echo json_encode([
            'success' => true,
            'data' => [
                'session_id' => (int)$existing['session_id'],
                'token' => $existing['token'] ?? '',
                'expires_at' => $existing['expires_at'] ?? '',
                'already_exists' => true
            ]
        ]);
        exit;
    }
    
    // Create new QR session
    $initialToken = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', time() + 10); // 10 seconds from now
    
    $insertStmt = $pdo->prepare('
        INSERT INTO attendance_qr_sessions (schedule_id, token, expires_at)
        VALUES (:schedule_id, :token, :expires_at)
    ');
    $insertStmt->execute([
        ':schedule_id' => $scheduleId,
        ':token' => $initialToken,
        ':expires_at' => $expiresAt,
    ]);
    
    $sessionId = (int)$pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'session_id' => $sessionId,
            'token' => $initialToken,
            'expires_at' => $expiresAt,
            'already_exists' => false
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error in start_qr_attendance.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to start QR attendance session.']);
}
