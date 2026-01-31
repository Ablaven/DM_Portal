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
$reason = trim((string)($_POST['reason'] ?? ''));

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

    $stmt = $pdo->prepare(
        'INSERT INTO doctor_slot_cancellations (week_id, doctor_id, day_of_week, slot_number, reason)
         VALUES (:week_id, :doctor_id, :day, :slot, :reason)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
    );

    $stmt->execute([
        ':week_id' => $weekId,
        ':doctor_id' => $doctorId,
        ':day' => $day,
        ':slot' => $slot,
        ':reason' => ($reason === '' ? null : $reason),
    ]);

    // Remove any scheduled entry for that slot (and backup it for undo)
    $removed = dmportal_cancel_and_remove_slots($pdo, [
        'week_id' => $weekId,
        'doctor_id' => $doctorId,
        'day' => $day,
        'slot' => $slot,
        'scope' => 'slot',
    ]);

    $pdo->commit();
    echo json_encode(['success'=>true,'data'=>['saved'=>true,'removed_slots'=>$removed]]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to set slot cancellation.',
        'details' => $e->getMessage(),
        'sql_errno' => (int)($e->errorInfo[1] ?? 0),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to set slot cancellation.',
        'details' => $e->getMessage(),
    ]);
}
