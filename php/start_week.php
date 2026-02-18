<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_week_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

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

$weekType = strtoupper(trim((string)($_POST['week_type'] ?? 'ACTIVE')));
if (!in_array($weekType, ['ACTIVE', 'PREP', 'RAMADAN'], true)) {
    bad_request('week_type must be ACTIVE, PREP, or RAMADAN.');
}
$isPrep = $weekType === 'PREP' ? 1 : 0;
$isRamadan = $weekType === 'RAMADAN' ? 1 : 0;

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();

    dmportal_ensure_weeks_prep_column($pdo);
    dmportal_ensure_weeks_ramadan_column($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_POST);

    if ($isPrep === 0) {
        // Close any existing active week for this term
        $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE status='active' AND term_id = :term_id");
        $stmt->execute([':term_id' => $termId]);
    }

    // Compute next label per term using the existing label numbers
    $stmt = $pdo->prepare("SELECT label FROM weeks WHERE term_id = :term_id ORDER BY week_id DESC");
    $stmt->execute([':term_id' => $termId]);
    $labels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $next = 1;
    foreach ($labels as $existingLabel) {
        if (preg_match('/(\d+)/', (string)$existingLabel, $m)) {
            $num = (int)$m[1];
            if ($num >= $next) {
                $next = $num + 1;
            }
        }
    }
    if ($isRamadan === 1) {
        $label = 'Ramadan Week ' . $next;
    } else {
        $label = ($isPrep === 1 ? 'Prep Week ' : 'Week ') . $next;
    }

    $status = $isPrep === 1 ? 'closed' : 'active';

    $stmt = $pdo->prepare("INSERT INTO weeks (term_id, label, start_date, status, is_prep, is_ramadan) VALUES (:term_id, :label, :start_date, :status, :is_prep, :is_ramadan)");
    $stmt->execute([
        ':term_id' => $termId,
        ':label' => $label,
        ':start_date' => $startDate,
        ':status' => $status,
        ':is_prep' => $isPrep,
        ':is_ramadan' => $isRamadan,
    ]);
    $newWeekId = (int)$pdo->lastInsertId();

    if ($isRamadan === 1) {
        $stmt = $pdo->prepare('UPDATE weeks SET is_ramadan = 0 WHERE term_id = :term_id AND week_id <> :week_id');
        $stmt->execute([':term_id' => $termId, ':week_id' => $newWeekId]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => $newWeekId,
            'label' => $label,
            'status' => $status,
            'is_prep' => $isPrep,
            'is_ramadan' => $isRamadan,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to start week.']);
}
