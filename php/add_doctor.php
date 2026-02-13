<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_schema_helpers.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $message): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$fullName = trim((string)($_POST['full_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
$color = strtoupper(trim((string)($_POST['color_code'] ?? '#0055A4')));
$doctorType = ucfirst(strtolower(trim((string)($_POST['doctor_type'] ?? 'Egyptian'))));

if ($fullName === '') bad_request('full_name is required.');
if ($email === '') bad_request('email is required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bad_request('email is invalid.');

// Phone is optional, but if provided must be plausible.
if ($phoneNumber !== '') {
    // Allow digits, spaces, parentheses, +, and dashes.
    if (!preg_match('/^[0-9+()\-\s]{6,32}$/', $phoneNumber)) {
        bad_request('phone_number is invalid. Use digits and optionally +, spaces, -, ().');
    }
}

if (!preg_match('/^#[0-9A-F]{6}$/', $color)) bad_request('color_code must be a hex color like #RRGGBB.');
if (!in_array($doctorType, ['Egyptian', 'French'], true)) bad_request('doctor_type must be Egyptian or French.');

function generate_unique_color(PDO $pdo, string $seed, int $maxTries = 25): string {
    for ($i = 0; $i < $maxTries; $i++) {
        $h = hash('sha256', $seed . '|' . $i);
        $hex = strtoupper(substr($h, 0, 6));
        // Nudge away from very-dark colors by XOR'ing with a constant
        $n = hexdec($hex) ^ 0x3366FF;
        $candidate = sprintf('#%06X', $n & 0xFFFFFF);

        $chk = $pdo->prepare('SELECT 1 FROM doctors WHERE color_code = :c LIMIT 1');
        $chk->execute([':c' => $candidate]);
        if (!$chk->fetchColumn()) return $candidate;
    }
    return '#0055A4';
}

try {
    $pdo = get_pdo();

    dmportal_ensure_doctor_type_column($pdo);

    // If chosen color is already taken, auto-generate a unique one to avoid breaking the form UX.
    $chk = $pdo->prepare('SELECT 1 FROM doctors WHERE color_code = :c LIMIT 1');
    $chk->execute([':c' => $color]);
    if ($chk->fetchColumn()) {
        $color = generate_unique_color($pdo, $email);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO doctors (full_name, email, phone_number, color_code, doctor_type) VALUES (:name, :email, :phone, :color, :type)'
        );
        $stmt->execute([':name' => $fullName, ':email' => $email, ':phone' => ($phoneNumber === '' ? null : $phoneNumber), ':color' => $color, ':type' => $doctorType]);
    } catch (PDOException $e) {
        // Backward compatibility: older DBs may not have doctors.phone_number yet.
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            $stmt = $pdo->prepare(
                'INSERT INTO doctors (full_name, email, color_code, doctor_type) VALUES (:name, :email, :color, :type)'
            );
            $stmt->execute([':name' => $fullName, ':email' => $email, ':color' => $color, ':type' => $doctorType]);
        } else {
            throw $e;
        }
    }

    echo json_encode(['success' => true, 'data' => ['doctor_id' => (int)$pdo->lastInsertId(), 'color_code' => $color, 'doctor_type' => $doctorType]]);
} catch (PDOException $e) {
    // 1062 duplicate
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('A doctor with this email already exists.');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add doctor.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add doctor.']);
}
