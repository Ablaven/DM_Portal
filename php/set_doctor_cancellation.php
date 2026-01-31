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
$reason = trim((string)($_POST['reason'] ?? ''));

$validDays = ['Sun','Mon','Tue','Wed','Thu'];
if ($weekId <= 0) bad_request('week_id is required.');
if ($doctorId <= 0) bad_request('doctor_id is required.');
if (!in_array($day, $validDays, true)) bad_request('Invalid day_of_week.');

try {
    $pdo = get_pdo();

    // Ensure backup table exists BEFORE starting a transaction (MySQL DDL causes implicit commit)
    dmportal_ensure_cancel_restore_table($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO doctor_week_cancellations (week_id, doctor_id, day_of_week, reason)
         VALUES (:week_id, :doctor_id, :day, :reason)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
    );

    $stmt->execute([
        ':week_id' => $weekId,
        ':doctor_id' => $doctorId,
        ':day' => $day,
        ':reason' => ($reason === '' ? null : $reason),
    ]);

    // Remove any scheduled slots for that day (and backup them for undo)
    $removed = dmportal_cancel_and_remove_slots($pdo, [
        'week_id' => $weekId,
        'doctor_id' => $doctorId,
        'day' => $day,
        'scope' => 'day',
    ]);

    $pdo->commit();
    echo json_encode(['success'=>true,'data'=>['saved'=>true,'removed_slots'=>$removed]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to set cancellation.']);
}
