<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';

auth_require_login(true);

auth_require_roles(['admin','management'], true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $label = trim((string)($_POST['label'] ?? ''));
    if ($label === '') bad_request('Category name is required.');

    $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
    $key = trim($key, '_');
    if ($key === '') bad_request('Invalid category name.');

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $stmt = $pdo->prepare('INSERT INTO evaluation_categories (category_key, label) VALUES (:key, :label) ON DUPLICATE KEY UPDATE label = VALUES(label)');
    $stmt->execute([':key' => $key, ':label' => $label]);

    echo json_encode(['success' => true, 'data' => ['category_key' => $key, 'label' => $label]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add category.']);
}
