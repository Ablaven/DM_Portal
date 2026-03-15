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
    // RAMADAN weeks behave like ACTIVE weeks (same scheduling behaviour, only timing differs in exports).
    $newStatus = ($weekType === 'ACTIVE' || $weekType === 'RAMADAN') ? 'active' : 'closed';

    if ($weekType === 'ACTIVE' || $weekType === 'RAMADAN') {
        // Close any other active week in this term when making a week active/ramadan.
        $stmt = $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE status='active' AND term_id = :term_id AND week_id <> :week_id");
        $stmt->execute([':term_id' => $termId, ':week_id' => $weekId]);
    }

    if ($weekType === 'RAMADAN') {
        // Only one Ramadan week per term: clear flag from all others in this term.
        $stmt = $pdo->prepare('UPDATE weeks SET is_ramadan = 0 WHERE term_id = :term_id AND week_id <> :week_id');
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
        if ($weekType === 'PREP') {
            $newLabel = 'Prep Week ' . $num;
        } elseif ($weekType === 'RAMADAN') {
            $newLabel = 'Ramadan Week ' . $num;
        } else {
            $newLabel = 'Week ' . $num;
        }
    }

    $update = $pdo->prepare('UPDATE weeks SET label = :label, is_prep = :is_prep, is_ramadan = :is_ramadan, status = :status WHERE week_id = :week_id');
    $update->execute([
        ':label' => $newLabel,
        ':is_prep' => $isPrep,
        ':is_ramadan' => $isRamadan,
        ':status' => $newStatus,
        ':week_id' => $weekId,
    ]);

    // If we just marked this week as RAMADAN, also normalize labels for all
    // *other* weeks in the term so they no longer contain stale "Ramadan"/"Prep"
    // wording that doesn't match their flags.
    if ($weekType === 'RAMADAN') {
        $otherStmt = $pdo->prepare('SELECT week_id, label, is_prep, is_ramadan FROM weeks WHERE term_id = :term_id AND week_id <> :week_id');
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
            $isPrepFlag = (int)($row['is_prep'] ?? 0) === 1;
            $newOtherLabel = $isPrepFlag ? ('Prep Week ' . $n) : ('Week ' . $n);
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
