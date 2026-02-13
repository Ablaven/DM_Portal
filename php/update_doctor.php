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

$doctorId = (int)($_POST['doctor_id'] ?? 0);
$fullName = trim((string)($_POST['full_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
$color = strtoupper(trim((string)($_POST['color_code'] ?? '')));
$doctorType = ucfirst(strtolower(trim((string)($_POST['doctor_type'] ?? 'Egyptian'))));

if ($doctorId <= 0) bad_request('doctor_id is required.');
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

try {
    $pdo = get_pdo();

    $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
    $chk->execute([':id' => $doctorId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Doctor not found.']);
        exit;
    }

    dmportal_ensure_doctor_type_column($pdo);

    // If color is already used by another doctor, return a specific error.
    $dupColor = $pdo->prepare('SELECT doctor_id FROM doctors WHERE color_code = :c AND doctor_id <> :id LIMIT 1');
    $dupColor->execute([':c' => $color, ':id' => $doctorId]);
    if ($dupColor->fetch()) {
        bad_request('This color is already used by another doctor. Please choose a different color.');
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE doctors
             SET full_name = :name, email = :email, phone_number = :phone, color_code = :color, doctor_type = :type
             WHERE doctor_id = :id'
        );
        $stmt->execute([':name' => $fullName, ':email' => $email, ':phone' => ($phoneNumber === '' ? null : $phoneNumber), ':color' => $color, ':type' => $doctorType, ':id' => $doctorId]);
    } catch (PDOException $e) {
        // Backward compatibility: older DBs may not have doctors.phone_number yet.
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            $stmt = $pdo->prepare(
                'UPDATE doctors
                 SET full_name = :name, email = :email, color_code = :color, doctor_type = :type
                 WHERE doctor_id = :id'
            );
            $stmt->execute([':name' => $fullName, ':email' => $email, ':color' => $color, ':type' => $doctorType, ':id' => $doctorId]);
        } else {
            throw $e;
        }
    }

    echo json_encode(['success' => true, 'data' => ['updated' => true, 'color_code' => $color, 'doctor_type' => $doctorType]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('A doctor with this email already exists.');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update doctor.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update doctor.']);
}
