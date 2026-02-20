<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

function bad_request(string $m): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    auth_require_api_access();
    auth_require_roles(['admin']);

    $label = trim((string)($_POST['label'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));

    if ($label === '' || !in_array($semester, [1, 2], true)) {
        bad_request('Label and a valid semester (1 or 2) are required.');
    }

    $pdo = get_pdo();
    $termId = dmportal_create_term($pdo, $label, $semester, $startDate !== '' ? $startDate : null, $endDate !== '' ? $endDate : null);

    echo json_encode(['success' => true, 'data' => ['term_id' => $termId]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create term.']);
}
