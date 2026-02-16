<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_roles(['admin','management'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed.']);
    exit;
}

try {
    $pdo = get_pdo();
    $termId = dmportal_get_term_id_from_request($pdo, $_POST);
    $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE status='active' AND term_id = :term_id");
    $stmt->execute([':term_id' => $termId]);

    echo json_encode(['success'=>true,'data'=>['stopped'=>true, 'term_id' => $termId]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to stop week.']);
}
