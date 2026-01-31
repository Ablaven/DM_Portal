<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);
require_once __DIR__ . '/_cancel_restore_helpers.php';

function bad_request(string $m): void { http_response_code(400); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed.']);
    exit;
}

$weekId = (int)($_POST['week_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$day = trim((string)($_POST['day_of_week'] ?? ''));
$slot = (int)($_POST['slot_number'] ?? 0);

$validDays = ['Sun','Mon','Tue','Wed','Thu'];
if ($weekId <= 0) bad_request('week_id is required.');
if ($doctorId <= 0) bad_request('doctor_id is required.');
if (!in_array($day, $validDays, true)) bad_request('Invalid day_of_week.');
if ($slot < 1 || $slot > 5) bad_request('slot_number must be 1-5.');

try {
    $pdo = get_pdo();

    // Ensure tables exist BEFORE starting a transaction (MySQL DDL causes implicit commit)
    dmportal_ensure_slot_cancellations_table($pdo);
    dmportal_ensure_cancel_restore_table($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('DELETE FROM doctor_slot_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');
    $stmt->execute([':week_id'=>$weekId, ':doctor_id'=>$doctorId, ':day'=>$day, ':slot'=>$slot]);

    // Restore removed entry for that slot
    $restored = dmportal_restore_cancelled_slots($pdo, [
        'week_id' => $weekId,
        'doctor_id' => $doctorId,
        'day' => $day,
        'slot' => $slot,
        'scope' => 'slot',
    ]);

    $pdo->commit();
    echo json_encode(['success'=>true,'data'=>['cleared'=>true,'restored_slots'=>$restored]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to clear slot cancellation.']);
}
