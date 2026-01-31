<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth_schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$email = trim((string)($_POST['email'] ?? 'admin@example.com'));
$fullName = trim((string)($_POST['full_name'] ?? 'Admin'));

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'username and password are required.']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    // Only allowed when there are no users.
    $count = count_portal_users($pdo);
    if ($count > 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'First admin already exists.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Insert into admins table (compat)
        $pdo->prepare('INSERT INTO admins (full_name, email, role) VALUES (:n,:e,:r)')
            ->execute([':n' => $fullName, ':e' => $email, ':r' => 'admin']);

        $stmt = $pdo->prepare('INSERT INTO portal_users (username, password_hash, role, is_active) VALUES (:u,:h,\'admin\',1)');
        $stmt->execute([
            ':u' => $username,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create first admin.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create first admin.']);
}
