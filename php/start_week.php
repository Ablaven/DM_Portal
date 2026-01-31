<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $m): void { http_response_code(400); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed.']);
    exit;
}

$startDate = trim((string)($_POST['start_date'] ?? ''));
if ($startDate === '') bad_request('start_date is required (YYYY-MM-DD).');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) bad_request('Invalid start_date format.');

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();

    // Close any existing active week
    $pdo->exec("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE status='active'");

    // Compute next label
    $max = $pdo->query("SELECT COALESCE(MAX(week_id),0) AS max_id FROM weeks")->fetch();
    $next = ((int)$max['max_id']) + 1;
    $label = 'Week ' . $next;

    $stmt = $pdo->prepare("INSERT INTO weeks (label, start_date, status) VALUES (:label, :start_date, 'active')");
    $stmt->execute([':label'=>$label, ':start_date'=>$startDate]);

    $pdo->commit();
    echo json_encode(['success'=>true,'data'=>['week_id'=>(int)$pdo->lastInsertId(),'label'=>$label]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to start week.']);
}
