<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';
require_once __DIR__ . '/_week_schema_helpers.php';

try {
    auth_require_api_access();
    auth_require_roles(['admin']);

    $termId = (int)($_POST['term_id'] ?? 0);
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    if ($termId <= 0) {
        bad_request('term_id is required.');
    }

    $pdo = get_pdo();
    $weekId = dmportal_reset_weeks_for_term($pdo, $termId, $startDate !== '' ? $startDate : null, false);

    echo json_encode(['success' => true, 'data' => ['term_id' => $termId, 'week_id' => $weekId]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to reset weeks for term.']);
}
