<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);

try {
    $pdo = get_pdo();
    $stmt = $pdo->query("SELECT week_id, label, start_date, end_date, status, created_at FROM weeks ORDER BY week_id DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch weeks.']);
}
