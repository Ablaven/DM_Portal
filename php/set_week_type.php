<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_week_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_roles(['admin', 'management'], true);

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

$weekId = (int)($_POST['week_id'] ?? 0);
$weekType = strtoupper(trim((string)($_POST['week_type'] ?? '')));

if ($weekId <= 0) {
    bad_request('week_id is required.');
}
if (!in_array($weekType, ['ACTIVE', 'PREP'], true)) {
    bad_request('week_type must be ACTIVE or PREP.');
}

try {
    $pdo = get_pdo();
    dmportal_ensure_weeks_prep_column($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT week_id, status, is_prep, term_id FROM weeks WHERE week_id = :week_id LIMIT 1');
    $stmt->execute([':week_id' => $weekId]);
    $week = $stmt->fetch();
    if (!$week) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Week not found.']);
        exit;
    }

    $termId = (int)($week['term_id'] ?? 0);
    if ($termId <= 0) {
        $termId = dmportal_get_active_term_id($pdo);
    }

    $isPrep = $weekType === 'PREP' ? 1 : 0;
    $newStatus = $weekType === 'ACTIVE' ? 'active' : 'closed';

    if ($weekType === 'ACTIVE') {
        $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE status='active' AND term_id = :term_id AND week_id <> :week_id");
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
    }

    $update = $pdo->prepare('UPDATE weeks SET is_prep = :is_prep, status = :status WHERE week_id = :week_id');
    $update->execute([
        ':is_prep' => $isPrep,
        ':status' => $newStatus,
        ':week_id' => $weekId,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => $weekId,
            'term_id' => $termId,
            'is_prep' => $isPrep,
            'status' => $newStatus,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update week type.']);
}
