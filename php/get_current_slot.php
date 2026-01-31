<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

// Determines the current slot based on server time and the active week.
// Days: Sun..Thu, Slots: 1..5 with fixed times.

function day_label_from_php_w(int $w): string {
    // PHP: 0=Sun .. 6=Sat
    return match ($w) {
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        default => ''
    };
}

function slot_from_minutes(int $mins): int {
    // Slot times:
    // 1 08:30–10:00
    // 2 10:10–11:30
    // 3 11:40–13:00
    // 4 13:10–14:40
    // 5 14:50–16:20
    $ranges = [
        1 => [8 * 60 + 30, 10 * 60 + 0],
        2 => [10 * 60 + 10, 11 * 60 + 30],
        3 => [11 * 60 + 40, 13 * 60 + 0],
        4 => [13 * 60 + 10, 14 * 60 + 40],
        5 => [14 * 60 + 50, 16 * 60 + 20],
    ];

    foreach ($ranges as $slot => [$start, $end]) {
        if ($mins >= $start && $mins <= $end) return $slot;
    }
    return 0;
}

try {
    $pdo = get_pdo();

    $wk = $pdo->query("SELECT week_id, start_date FROM weeks WHERE status='active' ORDER BY week_id DESC LIMIT 1")->fetch();
    if (!$wk) {
        echo json_encode(['success' => true, 'data' => ['week_id' => null, 'day_of_week' => null, 'slot_number' => null, 'in_schedule' => false]]);
        exit;
    }

    $now = new DateTimeImmutable('now');
    $dayLabel = day_label_from_php_w((int)$now->format('w'));

    $mins = ((int)$now->format('H')) * 60 + (int)$now->format('i');
    $slot = slot_from_minutes($mins);

    $inSchedule = ($dayLabel !== '' && $slot >= 1 && $slot <= 5);

    echo json_encode([
        'success' => true,
        'data' => [
            'week_id' => (int)$wk['week_id'],
            'day_of_week' => $inSchedule ? $dayLabel : null,
            'slot_number' => $inSchedule ? $slot : null,
            'server_time' => $now->format('Y-m-d H:i:s'),
            'in_schedule' => $inSchedule,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to determine current slot.']);
}
