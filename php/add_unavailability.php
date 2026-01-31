<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $m): void { http_response_code(400); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed. Use POST.']);
    exit;
}

$doctorId = (int)($_POST['doctor_id'] ?? 0);
$start = trim((string)($_POST['start_datetime'] ?? ''));
$end = trim((string)($_POST['end_datetime'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));

if ($doctorId <= 0) bad_request('doctor_id is required.');
if ($start === '' || $end === '') bad_request('start_datetime and end_datetime are required.');

try {
    $startDt = new DateTimeImmutable($start);
    $endDt = new DateTimeImmutable($end);
} catch (Throwable $e) {
    bad_request('Invalid datetime format.');
}

if ($endDt <= $startDt) bad_request('end_datetime must be after start_datetime.');

try {
    $pdo = get_pdo();

    $chkD = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chkD->execute([':id' => $doctorId]);
    if (!$chkD->fetch()) bad_request('Doctor not found.');

    $stmt = $pdo->prepare(
        'INSERT INTO doctor_unavailability (doctor_id, start_datetime, end_datetime, reason)
         VALUES (:doctor_id, :start_dt, :end_dt, :reason)'
    );

    $stmt->execute([
        ':doctor_id' => $doctorId,
        ':start_dt' => $startDt->format('Y-m-d H:i:s'),
        ':end_dt' => $endDt->format('Y-m-d H:i:s'),
        ':reason' => ($reason === '' ? null : $reason),
    ]);

    echo json_encode(['success'=>true,'data'=>['unavailability_id'=>(int)$pdo->lastInsertId()]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to add unavailability.']);
}
