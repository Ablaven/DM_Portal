<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_week_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

try {
    $pdo = get_pdo();
    dmportal_ensure_weeks_prep_column($pdo);

    $user = auth_current_user() ?: [];
    $role = strtolower((string)($user['role'] ?? ''));
    $isAdmin = in_array($role, ['admin', 'management'], true);

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    $activeWeekId = null;
    $activeStmt = $pdo->prepare("SELECT week_id FROM weeks WHERE status='active' AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
    $activeStmt->execute([':term_id' => $termId]);
    $activeWeekId = (int)($activeStmt->fetchColumn() ?: 0);

    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT week_id, label, start_date, end_date, status, created_at, is_prep FROM weeks WHERE term_id = :term_id ORDER BY week_id DESC");
        $stmt->execute([':term_id' => $termId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'active_week_id' => $activeWeekId, 'term_id' => $termId]);
        return;
    }

    if ($activeWeekId <= 0) {
        echo json_encode(['success' => true, 'data' => [], 'active_week_id' => null, 'term_id' => $termId]);
        return;
    }

    $stmt = $pdo->prepare("SELECT week_id, label, start_date, end_date, status, created_at, is_prep FROM weeks WHERE term_id = :term_id AND week_id <= :active_week_id ORDER BY week_id DESC");
    $stmt->execute([':term_id' => $termId, ':active_week_id' => $activeWeekId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'active_week_id' => $activeWeekId, 'term_id' => $termId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch weeks.']);
}
