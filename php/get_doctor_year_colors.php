<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

auth_require_roles(['admin','management'], true);

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($doctorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'doctor_id is required.']);
    exit;
}

try {
    $pdo = get_pdo();

    // Ensure table exists for upgraded schemas.
    dmportal_ensure_doctor_year_colors_table($pdo);

    // Fetch base doctor color for fallback.
    $doc = $pdo->prepare('SELECT doctor_id, color_code FROM doctors WHERE doctor_id = :id');
    $doc->execute([':id' => $doctorId]);
    $drow = $doc->fetch();
    if (!$drow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    $base = strtoupper((string)($drow['color_code'] ?? '#0055A4'));

    $stmt = $pdo->prepare('SELECT year_level, color_code FROM doctor_year_colors WHERE doctor_id = :id');
    $stmt->execute([':id' => $doctorId]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $yl = (int)($r['year_level'] ?? 0);
        if ($yl >= 1 && $yl <= 3) {
            $map[(string)$yl] = strtoupper((string)$r['color_code']);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'doctor_id' => $doctorId,
            'base_color_code' => $base,
            'year_colors' => $map,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch doctor year colors.']);
}
