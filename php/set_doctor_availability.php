<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_availability_schema_helpers.php';

auth_require_login(true);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');

if ($role !== 'admin' && $role !== 'management') {
    auth_require_roles(['teacher'], true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$doctorIdRaw = $_POST['doctor_id'] ?? '';
$doctorId = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;

$weekIdRaw = $_POST['week_id'] ?? '';
$weekId = is_numeric($weekIdRaw) ? (int)$weekIdRaw : 0;

$day = strtoupper(trim((string)($_POST['day_of_week'] ?? '')));
$slotRaw = $_POST['slot_number'] ?? '';
$slot = is_numeric($slotRaw) ? (int)$slotRaw : 0;
$action = trim((string)($_POST['action'] ?? 'toggle'));

$validDays = ['SUN', 'MON', 'TUE', 'WED', 'THU'];
$validSlots = [1, 2, 3, 4, 5];

if ($role === 'teacher') {
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Teacher account is missing doctor_id.']);
        exit;
    }
    $doctorId = $ownId;
}

if ($doctorId <= 0 || $weekId <= 0 || !in_array($day, $validDays, true) || !in_array($slot, $validSlots, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'doctor_id, week_id, day_of_week, slot_number are required.']);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_doctor_availability_table($pdo);

    // validate doctor
    $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    if ($action === 'remove') {
        $del = $pdo->prepare('DELETE FROM doctor_availability WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
        $del->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
        echo json_encode(['success' => true, 'data' => ['status' => 'removed']]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT availability_id FROM doctor_availability WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
    $stmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
    $row = $stmt->fetch();

    if ($action === 'add') {
        if ($row) {
            echo json_encode(['success' => true, 'data' => ['status' => 'exists']]);
            exit;
        }
        $ins = $pdo->prepare('INSERT INTO doctor_availability (week_id, doctor_id, day_of_week, slot_number) VALUES (:week_id, :doctor_id, :day, :slot)');
        $ins->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);
        echo json_encode(['success' => true, 'data' => ['status' => 'added']]);
        exit;
    }

    if ($row) {
        $del = $pdo->prepare('DELETE FROM doctor_availability WHERE availability_id = :id');
        $del->execute([':id' => $row['availability_id']]);
        echo json_encode(['success' => true, 'data' => ['status' => 'removed']]);
        exit;
    }

    $ins = $pdo->prepare('INSERT INTO doctor_availability (week_id, doctor_id, day_of_week, slot_number) VALUES (:week_id, :doctor_id, :day, :slot)');
    $ins->execute([':week_id' => $weekId, ':doctor_id' => $doctorId, ':day' => $day, ':slot' => $slot]);

    echo json_encode(['success' => true, 'data' => ['status' => 'added']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update availability.']);
}
