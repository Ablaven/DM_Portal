<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_auth_schema.php';

auth_require_roles(['admin'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = trim((string)($_POST['role'] ?? ''));
$doctorId = isset($_POST['doctor_id']) && $_POST['doctor_id'] !== '' ? (int)$_POST['doctor_id'] : null;
$studentId = isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;

$allowedPages = $_POST['allowed_pages'] ?? null;
if (is_string($allowedPages)) {
    $allowedPages = [$allowedPages];
}

$validRoles = ['admin', 'management', 'teacher', 'student'];
if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'username and password are required.']);
    exit;
}
if (!in_array($role, $validRoles, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role.']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

// Role-specific ID handling:
// - admin: no IDs
// - teacher: doctor_id required
// - student: student_id required
if ($role === 'admin' || $role === 'management') {
    $doctorId = null;
    $studentId = null;
}
if ($role === 'teacher') {
    $studentId = null;
    if (!$doctorId || $doctorId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'doctor_id is required for teacher accounts.']);
        exit;
    }
}
if ($role === 'student') {
    $doctorId = null;
    if (!$studentId || $studentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'student_id is required for student accounts.']);
        exit;
    }
}

$allowedJson = null;
if (is_array($allowedPages) && count($allowedPages) > 0) {
    $allowed = array_values(array_unique(array_filter(array_map('strval', $allowedPages), fn($v) => $v !== '')));
    $allowedJson = json_encode($allowed, JSON_UNESCAPED_SLASHES);
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    // Validate referenced entities exist.
    if ($doctorId !== null) {
        $chk = $pdo->prepare('SELECT doctor_id FROM doctors WHERE doctor_id = :id');
        $chk->execute([':id' => $doctorId]);
        if (!$chk->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'doctor_id not found.']);
            exit;
        }
    }
    if ($studentId !== null) {
        $chk = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
        $chk->execute([':id' => $studentId]);
        if (!$chk->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'student_id not found.']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO portal_users (username, password_hash, role, doctor_id, student_id, allowed_pages_json) '
        . 'VALUES (:u, :h, :r, :did, :sid, :ap)'
    );

    $stmt->execute([
        ':u' => $username,
        ':h' => password_hash($password, PASSWORD_DEFAULT),
        ':r' => $role,
        ':did' => $doctorId,
        ':sid' => $studentId,
        ':ap' => $allowedJson,
    ]);

    echo json_encode(['success' => true, 'data' => ['user_id' => (int)$pdo->lastInsertId()]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create user.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create user.']);
}
