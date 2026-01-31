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

$doctorIdRaw = $_GET['doctor_id'] ?? '';
$doctorId = is_numeric($doctorIdRaw) ? (int)$doctorIdRaw : 0;

$weekIdRaw = $_GET['week_id'] ?? '';
$weekId = is_numeric($weekIdRaw) ? (int)$weekIdRaw : 0;

// Enforce teacher ownership ALWAYS.
if ($role === 'teacher') {
    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Teacher account is missing doctor_id.']);
        exit;
    }
    $doctorId = $ownId;
}

if ($weekId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'week_id is required.']);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_doctor_availability_table($pdo);

    if ($doctorId > 0) {
        $stmt = $pdo->prepare(
            'SELECT a.availability_id, a.week_id, a.doctor_id, a.day_of_week, a.slot_number, d.full_name
             FROM doctor_availability a
             JOIN doctors d ON d.doctor_id = a.doctor_id
             WHERE a.week_id = :week_id AND a.doctor_id = :doctor_id'
        );
        $stmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT a.availability_id, a.week_id, a.doctor_id, a.day_of_week, a.slot_number, d.full_name
             FROM doctor_availability a
             JOIN doctors d ON d.doctor_id = a.doctor_id
             WHERE a.week_id = :week_id'
        );
        $stmt->execute([':week_id' => $weekId]);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $stmt->fetchAll(),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch availability.']);
}
