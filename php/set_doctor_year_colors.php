<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

auth_require_roles(['admin','management'], true);

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

$doctorId = (int)($_POST['doctor_id'] ?? 0);
$yearLevel = (int)($_POST['year_level'] ?? 0);
$color = strtoupper(trim((string)($_POST['color_code'] ?? '')));

if ($doctorId <= 0) bad_request('doctor_id is required.');
if ($yearLevel < 1 || $yearLevel > 3) bad_request('year_level must be 1-3.');
if (!preg_match('/^#[0-9A-F]{6}$/', $color)) bad_request('color_code must be a hex color like #RRGGBB.');

try {
    $pdo = get_pdo();

    // Ensure schema exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    // Upsert
    $stmt = $pdo->prepare(
        'INSERT INTO doctor_year_colors (doctor_id, year_level, color_code) VALUES (:doctor_id, :year_level, :color) '
        .'ON DUPLICATE KEY UPDATE color_code = VALUES(color_code)'
    );
    $stmt->execute([':doctor_id' => $doctorId, ':year_level' => $yearLevel, ':color' => $color]);

    echo json_encode(['success' => true, 'data' => ['saved' => true]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save doctor year color.']);
}
