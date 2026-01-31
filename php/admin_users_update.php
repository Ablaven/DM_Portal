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

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id is required.']);
    exit;
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : null;
$role = isset($_POST['role']) ? trim((string)$_POST['role']) : null;
$doctorId = array_key_exists('doctor_id', $_POST) && $_POST['doctor_id'] !== '' ? (int)$_POST['doctor_id'] : null;
$studentId = array_key_exists('student_id', $_POST) && $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;

$allowedPages = $_POST['allowed_pages'] ?? null;
if (is_string($allowedPages)) $allowedPages = [$allowedPages];

$validRoles = ['admin', 'management', 'teacher', 'student'];
if ($role !== null && !in_array($role, $validRoles, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role.']);
    exit;
}

$allowedJson = null;
if (is_array($allowedPages)) {
    $allowed = array_values(array_unique(array_filter(array_map('strval', $allowedPages), fn($v) => $v !== '')));
    // If empty array explicitly provided, store NULL to mean "role default".
    $allowedJson = count($allowed) ? json_encode($allowed, JSON_UNESCAPED_SLASHES) : null;
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    // Load current user for validations.
    $curStmt = $pdo->prepare('SELECT user_id, role FROM portal_users WHERE user_id = :id');
    $curStmt->execute([':id' => $userId]);
    $cur = $curStmt->fetch();
    if (!$cur) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    $nextRole = $role ?? (string)$cur['role'];

    // Role-specific ID handling
    if ($nextRole === 'admin' || $nextRole === 'management') {
        $doctorId = null;
        $studentId = null;
    }

    if ($nextRole === 'teacher') {
        $studentId = null;
        if (!$doctorId || $doctorId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'doctor_id is required for teacher accounts.']);
            exit;
        }
    }

    if ($nextRole === 'student') {
        $doctorId = null;
        if (!$studentId || $studentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'student_id is required for student accounts.']);
            exit;
        }
    }

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

    $sets = [];
    $params = [':id' => $userId];

    if ($username !== null) {
        if ($username === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'username cannot be empty.']);
            exit;
        }
        $sets[] = 'username = :username';
        $params[':username'] = $username;
    }

    if ($role !== null) {
        $sets[] = 'role = :role';
        $params[':role'] = $role;
    }

    if (array_key_exists('doctor_id', $_POST)) {
        $sets[] = 'doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctorId;
    }

    if (array_key_exists('student_id', $_POST)) {
        $sets[] = 'student_id = :student_id';
        $params[':student_id'] = $studentId;
    }

    if ($allowedPages !== null) {
        $sets[] = 'allowed_pages_json = :allowed_pages_json';
        $params[':allowed_pages_json'] = $allowedJson;
    }

    if ($isActive !== null) {
        $sets[] = 'is_active = :is_active';
        $params[':is_active'] = $isActive ? 1 : 0;
    }

    if (!$sets) {
        echo json_encode(['success' => true, 'data' => ['updated' => false]]);
        exit;
    }

    $sql = 'UPDATE portal_users SET ' . implode(', ', $sets) . ' WHERE user_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update user.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update user.']);
}
