<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);
auth_require_roles(['teacher'], true);

// Close QR attendance session
// Marks session as closed, preventing reopening for that slot

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    
    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_id is required.']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get session info
    $stmt = $pdo->prepare('
        SELECT qs.session_id, qs.schedule_id, qs.is_closed,
               ds.doctor_id
        FROM attendance_qr_sessions qs
        JOIN doctor_schedules ds ON ds.schedule_id = qs.schedule_id
        WHERE qs.session_id = :sid
        LIMIT 1
    ');
    $stmt->execute([':sid' => $sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'QR session not found.']);
        exit;
    }
    
    // Verify teacher owns this session
    $u = auth_current_user();
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0 || (int)$session['doctor_id'] !== $ownId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }
    
    // Close the session
    $updateStmt = $pdo->prepare('
        UPDATE attendance_qr_sessions
        SET is_closed = 1
        WHERE session_id = :sid
    ');
    $updateStmt->execute([':sid' => $sessionId]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'session_id' => $sessionId,
            'closed' => true
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error in close_qr_session.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to close QR session.']);
}
