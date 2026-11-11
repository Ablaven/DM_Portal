<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';
require_once __DIR__ . '/email_all_teachers_schedule.php';

header('Content-Type: application/json');

auth_require_roles(['admin', 'management'], true);

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
$weekId = (int)($input['week_id'] ?? 0);

try {
    $pdo = get_pdo();

    // Get active week if not specified
    if ($weekId <= 0) {
        $termId = dmportal_get_term_id_from_request($pdo, $input);
        $stmt = $pdo->prepare("SELECT week_id, label, is_ramadan FROM weeks WHERE (status='active' OR is_ramadan=1) AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
        $stmt->execute([':term_id' => $termId]);
        $wk = $stmt->fetch();
        if (!$wk) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No active week found']);
            exit;
        }
        $weekId = (int)$wk['week_id'];
        $isRamadan = (int)($wk['is_ramadan'] ?? 0) === 1;
    } else {
        $wkStmt = $pdo->prepare('SELECT is_ramadan FROM weeks WHERE week_id = :id');
        $wkStmt->execute([':id' => $weekId]);
        $wk = $wkStmt->fetch();
        $isRamadan = ($wk && (int)($wk['is_ramadan'] ?? 0) === 1);
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

    $sent = 0;
    $errors = [];
    $sentList = [];

    // Only send to teachers WITH schedules
    foreach ($doctors as $doctor) {
        $doctorId = (int)$doctor['doctor_id'];
        $doctorName = (string)$doctor['full_name'];
        
        // Skip if doctor has no schedule
        if (!isset($doctorsWithSchedulesSet[$doctorId])) {
            continue;
        }

        // Send regular schedule email with Excel attachment
        try {
            $emailResult = sendDoctorScheduleEmail($pdo, $doctorId, $weekId);
            if ($emailResult['success']) {
                $sent++;
                $sentList[] = $doctorName;
            } else {
                $errors[] = "{$doctorName}: {$emailResult['error']}";
            }
        } catch (Throwable $e) {
            $errors[] = "{$doctorName}: {$e->getMessage()}";
        }
    }

    $weekType = $isRamadan ? 'Ramadan' : 'Normal';
    $message = "Sent {$sent} schedule email(s) for the active {$weekType} week.";
    
    if (!empty($errors)) {
        $message .= " Errors: " . implode('; ', $errors);
    }

    echo json_encode([
        'success' => true,
        'sent' => $sent,
        'sent_list' => $sentList,
        'errors' => $errors,
        'message' => $message
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
