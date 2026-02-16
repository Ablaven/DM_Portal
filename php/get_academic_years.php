<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

try {
    auth_require_api_access();
    auth_require_roles(['admin']);

    $pdo = get_pdo();
    dmportal_ensure_terms_table($pdo);

    $stmt = $pdo->query('SELECT academic_year_id, label, status, start_date, end_date, created_at FROM academic_years ORDER BY academic_year_id DESC');
    $years = $stmt->fetchAll() ?: [];
    echo json_encode([
        'success' => true,
        'data' => $years,
        'active_academic_year_id' => dmportal_get_active_academic_year_id($pdo),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load academic years.']);
}
