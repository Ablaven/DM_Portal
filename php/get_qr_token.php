<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);
auth_require_roles(['teacher'], true);

// Get current valid QR token for a session
// Auto-rotates token every 5 seconds
// Returns current token or generates new one if expired

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    
    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_id is required.']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get session info
    $stmt = $pdo->prepare('
        SELECT qs.session_id, qs.schedule_id, qs.token, qs.expires_at, qs.is_closed,
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
    
    // Check if session is closed
    if ((int)$session['is_closed'] === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'QR session has been closed.']);
        exit;
    }
    
    $now = time();
    $expiresAt = strtotime($session['expires_at']);
    
    // If token hasn't expired yet, return current token
    if ($expiresAt > $now) {
        echo json_encode([
            'success' => true,
            'data' => [
                'token' => $session['token'],
                'expires_at' => $session['expires_at'],
                'seconds_remaining' => $expiresAt - $now
            ]
        ]);
        exit;
    }
    
    // Token expired - generate new one
    $newToken = bin2hex(random_bytes(16));
    $newExpiresAt = date('Y-m-d H:i:s', $now + 10); // 10 seconds from now
    
    $updateStmt = $pdo->prepare('
        UPDATE attendance_qr_sessions
        SET token = :token, expires_at = :expires_at
        WHERE session_id = :sid
    ');
    $updateStmt->execute([
        ':token' => $newToken,
        ':expires_at' => $newExpiresAt,
        ':sid' => $sessionId,
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $newToken,
            'expires_at' => $newExpiresAt,
            'seconds_remaining' => 10,
            'rotated' => true
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error in get_qr_token.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to get QR token.']);
}
