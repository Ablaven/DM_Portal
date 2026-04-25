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
if (!in_array($weekType, ['ACTIVE', 'PREP', 'RAMADAN'], true)) {
    bad_request('week_type must be ACTIVE, PREP, or RAMADAN.');
}

try {
    $pdo = get_pdo();
    dmportal_ensure_weeks_prep_column($pdo);
    dmportal_ensure_weeks_ramadan_column($pdo);

    $pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT week_id, label, status, is_prep, is_ramadan, term_id FROM weeks WHERE week_id = :week_id LIMIT 1');
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
    $isRamadan = $weekType === 'RAMADAN' ? 1 : 0;
    // RAMADAN behaves like ACTIVE for scheduling, but keeps its own flag for timing UI/exports.
    $newStatus = ($weekType === 'ACTIVE' || $weekType === 'RAMADAN') ? 'active' : 'closed';

    // Keep one holder for each state in a term and ensure selected week's state wins.
    if ($weekType === 'ACTIVE') {
        $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()), is_ramadan = 0 WHERE term_id = :term_id AND week_id <> :week_id AND (status='active' OR is_ramadan=1)");
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
    } elseif ($weekType === 'RAMADAN') {
        $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE term_id = :term_id AND week_id <> :week_id AND status='active'");
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
        $stmt = $pdo->prepare('UPDATE weeks SET is_ramadan = 0 WHERE term_id = :term_id AND week_id <> :week_id');
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
    } elseif ($weekType === 'PREP') {
        $stmt = $pdo->prepare('UPDATE weeks SET is_prep = 0 WHERE term_id = :term_id AND week_id <> :week_id');
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
    }

    // Derive numeric part from existing label (first integer we find).
    $label = (string)($week['label'] ?? '');
    $num = 0;
    if (preg_match('/(\d+)/', $label, $m)) {
        $num = (int)$m[1];
    }
    if ($num <= 0) {
        // Fallback: keep existing label if we cannot parse a number.
        $newLabel = $label;
    } else {
        // Keep canonical labels as "Week N". Type/status are represented by
        // flags and status fields, not label wording.
        $newLabel = 'Week ' . $num;
    }

    $update = $pdo->prepare('UPDATE weeks SET label = :label, is_prep = :is_prep, is_ramadan = :is_ramadan, status = :status WHERE week_id = :week_id');
    $update->execute([
        ':label' => $newLabel,
        ':is_prep' => $isPrep,
        ':is_ramadan' => $isRamadan,
        ':status' => $newStatus,
        ':week_id' => $weekId,
    ]);

    // Normalize labels for all other weeks in the term so stale historical
    // wording ("Prep Week", "Ramadan Week") is not kept in labels.
    if (true) {
        $otherStmt = $pdo->prepare('SELECT week_id, label FROM weeks WHERE term_id = :term_id AND week_id <> :week_id');
        $otherStmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
        $others = $otherStmt->fetchAll();
        foreach ($others as $row) {
            $wid = (int)($row['week_id'] ?? 0);
            if ($wid <= 0) {
                continue;
            }
            $rawLabel = (string)($row['label'] ?? '');
            $n = 0;
            if (preg_match('/(\d+)/', $rawLabel, $mm)) {
                $n = (int)$mm[1];
            }
            if ($n <= 0) {
                continue;
            }
            $newOtherLabel = 'Week ' . $n;
            if ($newOtherLabel !== $rawLabel) {
                $upd = $pdo->prepare('UPDATE weeks SET label = :label WHERE week_id = :week_id');
                $upd->execute([':label' => $newOtherLabel, ':week_id' => $wid]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => $weekId,
            'term_id' => $termId,
            'is_prep' => $isPrep,
            'is_ramadan' => $isRamadan,
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
