<?php

declare(strict_types=1);

// Catch all errors and output as JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/_auth.php';
    require_once __DIR__ . '/db_connect.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
    exit;
}

// Require admin/management/teacher roles
try {
    auth_require_roles(['admin', 'management', 'teacher']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Authentication failed: ' . $e->getMessage()]);
    exit;
}

$u = auth_current_user();
$userRole = (string)($u['role'] ?? '');

// Get parameters
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$weekId = isset($_POST['week_id']) ? (int)$_POST['week_id'] : 0;
$day = isset($_POST['day']) ? trim((string)$_POST['day']) : '';
$slot = isset($_POST['slot']) ? (int)$_POST['slot'] : 0;
$reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : '';

// Validation
if (!$doctorId || !$weekId || !$day || !$slot) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Teachers can only set their own unavailability
if ($userRole === 'teacher') {
    $userDoctorId = (int)($u['doctor_id'] ?? 0);
    if ($doctorId !== $userDoctorId) {
        echo json_encode(['success' => false, 'error' => 'You can only set your own unavailability']);
        exit;
    }
}

// Valid days
$validDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
if (!in_array($day, $validDays, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid day']);
    exit;
}

// Valid slots
if ($slot < 1 || $slot > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid slot']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get week start date to calculate actual datetime
    $stmt = $pdo->prepare("SELECT start_date FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$week) {
        echo json_encode(['success' => false, 'error' => 'Week not found']);
        exit;
    }
    
    // Calculate the actual date based on week start and day
    $dayOffsets = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4];
    $dayOffset = $dayOffsets[$day];
    
    $slotTimes = [
        1 => ['start' => '08:30:00', 'end' => '10:00:00'],
        2 => ['start' => '10:10:00', 'end' => '11:30:00'],
        3 => ['start' => '11:40:00', 'end' => '13:00:00'],
        4 => ['start' => '13:10:00', 'end' => '14:40:00'],
        5 => ['start' => '14:50:00', 'end' => '16:20:00'],
    ];
    
    $startDate = new DateTime($week['start_date']);
    $startDate->modify("+{$dayOffset} days");
    $startDateStr = $startDate->format('Y-m-d');
    
    $startDatetime = $startDateStr . ' ' . $slotTimes[$slot]['start'];
    $endDatetime = $startDateStr . ' ' . $slotTimes[$slot]['end'];
    
    // Check if unavailability already exists for this slot
    $checkStmt = $pdo->prepare("
        SELECT unavailability_id 
        FROM doctor_unavailability 
        WHERE doctor_id = ? 
        AND start_datetime = ? 
        AND end_datetime = ?
    ");
    $checkStmt->execute([$doctorId, $startDatetime, $endDatetime]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Unavailability already exists for this slot']);
        exit;
    }
    
    // Insert unavailability
    $insertStmt = $pdo->prepare("
        INSERT INTO doctor_unavailability (doctor_id, start_datetime, end_datetime, reason, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $insertStmt->execute([$doctorId, $startDatetime, $endDatetime, $reason]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Unavailability added successfully',
        'unavailability_id' => (int)$pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("add_unavailability_slot error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
