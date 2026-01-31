<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $message): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$doctorId = (int)($_POST['doctor_id'] ?? 0);
if ($doctorId <= 0) bad_request('doctor_id is required.');

try {
    $pdo = get_pdo();

    $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    // refuse delete if schedules exist for this doctor
    $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM doctor_schedules WHERE doctor_id = :id');
    $cnt->execute([':id' => $doctorId]);
    if ((int)($cnt->fetch()['c'] ?? 0) > 0) {
        bad_request('Cannot delete: doctor is used in schedule. Remove schedule slots first.');
    }

    // Remove unavailability records (FK CASCADE is set in schema, but be defensive)
    try {
        $pdo->prepare('DELETE FROM doctor_unavailability WHERE doctor_id = :id')->execute([':id' => $doctorId]);
    } catch (Throwable $e) {
        // ignore if table missing
    }

    $del = $pdo->prepare('DELETE FROM doctors WHERE doctor_id = :id');
    $del->execute([':id' => $doctorId]);

    echo json_encode(['success' => true, 'data' => ['deleted' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete doctor.']);
}
