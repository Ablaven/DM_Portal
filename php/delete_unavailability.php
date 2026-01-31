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

$id = (int)($_POST['unavailability_id'] ?? 0);
if ($id <= 0) bad_request('unavailability_id is required.');

try {
    $pdo = get_pdo();
    $del = $pdo->prepare('DELETE FROM doctor_unavailability WHERE unavailability_id = :id');
    $del->execute([':id' => $id]);
    echo json_encode(['success'=>true,'data'=>['deleted'=>true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to delete unavailability.']);
}
