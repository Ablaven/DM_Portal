<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);
$u = auth_current_user();
if (($u['role'] ?? '') !== 'admin') {
    auth_require_roles(['teacher'], true);
}

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

if (!auth_is_allowed_pages_override_mode() && (($u['role'] ?? '') === 'teacher')) {
    $doctorId = (int)($u['doctor_id'] ?? 0);
}
$weekId = isset($_GET['week_id']) ? (int)$_GET['week_id'] : 0;

if ($doctorId <= 0 || $weekId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'doctor_id and week_id are required.']);
    exit;
}

try {
    $pdo = get_pdo();

    $wk = $pdo->prepare('SELECT start_date FROM weeks WHERE week_id = :id');
    $wk->execute([':id' => $weekId]);
    $week = $wk->fetch();
    if (!$week) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Week not found.']);
        exit;
    }

    $start = new DateTimeImmutable($week['start_date'] . ' 00:00:00');
    $end = $start->modify('+7 days');

    $stmt = $pdo->prepare(
        'SELECT unavailability_id, start_datetime, end_datetime, reason
         FROM doctor_unavailability
         WHERE doctor_id = :doctor_id
           AND start_datetime < :end_dt
           AND end_datetime > :start_dt
         ORDER BY start_datetime ASC'
    );

    $stmt->execute([
        ':doctor_id' => $doctorId,
        ':start_dt' => $start->format('Y-m-d H:i:s'),
        ':end_dt' => $end->format('Y-m-d H:i:s'),
    ]);

    echo json_encode(['success' => true, 'data' => ['items' => $stmt->fetchAll()]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch unavailability.']);
}
