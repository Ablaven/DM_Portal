<?php

declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$code = isset($payload['code']) ? trim((string)$payload['code']) : '';

if ($code !== '700') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid code.']);
    exit;
}

require_once __DIR__ . '/_easter_egg_gate.php';

dmportal_grant_easter_egg();

echo json_encode(['success' => true, 'data' => ['ok' => true]]);
