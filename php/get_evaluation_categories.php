<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';

auth_require_login(true);

try {
    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);
    dmportal_eval_seed_categories($pdo);

    $stmt = $pdo->query('SELECT category_key, label FROM evaluation_categories ORDER BY sort_order ASC, category_key ASC');
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load categories.']);
}
