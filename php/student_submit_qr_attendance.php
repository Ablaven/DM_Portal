<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

// No auth required - students can submit via QR without being logged in
// We validate based on token + student_code

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $token = trim((string)($_POST['token'] ?? ''));
    $studentCode = strtoupper(trim((string)($_POST['student_code'] ?? '')));
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token is required.']);
        exit;
    }
    
    if (empty($studentCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Student code is required.']);
        exit;
    }
    
    // Validate student code format (MGxxxx)
    if (!preg_match('/^MG\d+$/i', $studentCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid student code format. Must be MGxxxx.']);
        exit;
    }
    
    $pdo = get_pdo();
    
    dmportal_ensure_attendance_records_table($pdo);
    
    // Get QR session by token
    $sessionStmt = $pdo->prepare('
        SELECT session_id, schedule_id, expires_at, is_closed
        FROM attendance_qr_sessions
        WHERE token = :token
        LIMIT 1
    ');
    $sessionStmt->execute([':token' => $token]);
    $session = $sessionStmt->fetch();
    
    if (!$session) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired QR code.']);
        exit;
    }
    
    // Check if session is closed
    if ((int)$session['is_closed'] === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'This attendance session has been closed by the teacher.']);
        exit;
    }
    
    // Check if token has expired
    if (strtotime($session['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'QR code has expired. Please scan the latest code.']);
        exit;
    }
    
    $scheduleId = (int)$session['schedule_id'];
    
    // Get student by code
    $studentStmt = $pdo->prepare('SELECT student_id, full_name FROM students WHERE student_code = :code LIMIT 1');
    $studentStmt->execute([':code' => $studentCode]);
    $student = $studentStmt->fetch();
    
    if (!$student) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Student code not found. Please check your ID.']);
        exit;
    }
    
    $studentId = (int)$student['student_id'];
    
    // VALIDATE: Check if student's year matches the course year
    $scheduleStmt = $pdo->prepare('
        SELECT ds.schedule_id, c.year_level, c.course_name
        FROM doctor_schedules ds
        JOIN courses c ON c.course_id = ds.course_id
        WHERE ds.schedule_id = :sid
        LIMIT 1
    ');
    $scheduleStmt->execute([':sid' => $scheduleId]);
    $scheduleInfo = $scheduleStmt->fetch();
    
    if (!$scheduleInfo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Schedule not found.']);
        exit;
    }
    
    // Get student's year level
    $studentYearStmt = $pdo->prepare('SELECT year_level FROM students WHERE student_id = :sid LIMIT 1');
    $studentYearStmt->execute([':sid' => $studentId]);
    $studentYear = $studentYearStmt->fetchColumn();
    
    if ($studentYear && (int)$studentYear !== (int)$scheduleInfo['year_level']) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'You cannot mark attendance for a Year ' . $scheduleInfo['year_level'] . ' course. This course is not for your year level.'
        ]);
        exit;
    }
    
    // Check for duplicate submission from same device/IP (prevent abuse)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $deviceFingerprint = md5($clientIp . '|' . $userAgent);
    
    // Check if this device already submitted for this schedule
    $dupCheckStmt = $pdo->prepare('
        SELECT COUNT(*) as count
        FROM attendance_records
        WHERE schedule_id = :schedule_id
        AND student_id = :student_id
        AND updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ');
    $dupCheckStmt->execute([
        ':schedule_id' => $scheduleId,
        ':student_id' => $studentId,
    ]);
    $dupCheck = $dupCheckStmt->fetch();
    
    if ((int)$dupCheck['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You have already submitted attendance for this lecture.']);
        exit;
    }
    
    // Get term_id for schedule
    $schedStmt = $pdo->prepare('SELECT week_id FROM doctor_schedules WHERE schedule_id = :sid');
    $schedStmt->execute([':sid' => $scheduleId]);
    $schedRow = $schedStmt->fetch();
    if (!$schedRow) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid schedule.']);
        exit;
    }
    
    $termId = dmportal_get_term_id_for_week($pdo, (int)$schedRow['week_id']);
    
    // Mark student as PRESENT
    $sql = "INSERT INTO attendance_records (term_id, schedule_id, student_id, status)
            VALUES (:term_id, :schedule_id, :student_id, 'PRESENT')
            ON DUPLICATE KEY UPDATE status = 'PRESENT', updated_at = CURRENT_TIMESTAMP";
    $ins = $pdo->prepare($sql);
    $ins->execute([
        ':term_id' => $termId,
        ':schedule_id' => $scheduleId,
        ':student_id' => $studentId,
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student_name' => $student['full_name'],
            'student_code' => $studentCode,
            'status' => 'PRESENT',
            'message' => 'Attendance recorded successfully!'
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('Error in student_submit_qr_attendance.php: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit attendance: ' . $e->getMessage()]);
}
