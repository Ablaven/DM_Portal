<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

header('Content-Type: application/json');

auth_require_roles(['admin', 'management'], true);

$weekId = (int)($_GET['week_id'] ?? 0);

try {
    $pdo = get_pdo();

    // Get active week if not specified
    if ($weekId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Week ID is required']);
        exit;
    }

    // Get all doctors with email addresses
    $doctorsStmt = $pdo->prepare(
        'SELECT doctor_id, full_name, email 
         FROM doctors 
         WHERE email IS NOT NULL AND TRIM(email) != ""
         ORDER BY full_name ASC'
    );
    $doctorsStmt->execute();
    $doctors = $doctorsStmt->fetchAll();

    if (empty($doctors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No doctors with email addresses found']);
        exit;
    }

    // Check which doctors have schedules for this week
    $scheduleCheckStmt = $pdo->prepare(
        'SELECT DISTINCT doctor_id 
         FROM doctor_schedules 
         WHERE week_id = :week_id'
    );
    $scheduleCheckStmt->execute([':week_id' => $weekId]);
    $doctorsWithSchedules = array_column($scheduleCheckStmt->fetchAll(), 'doctor_id');
    $doctorsWithSchedulesSet = array_flip($doctorsWithSchedules);

    $willSend = [];
    $willSkip = [];

    foreach ($doctors as $doctor) {
        $doctorId = (int)$doctor['doctor_id'];
        $doctorName = (string)$doctor['full_name'];
        
        if (isset($doctorsWithSchedulesSet[$doctorId])) {
            $willSend[] = $doctorName;
        } else {
            $willSkip[] = $doctorName;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'will_send' => $willSend,
            'will_skip' => $willSkip,
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
