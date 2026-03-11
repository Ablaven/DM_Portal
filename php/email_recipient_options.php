<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json');

auth_require_roles(['admin', 'management'], true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$category = '';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    $category = (string)($input['category'] ?? '');
} else {
    $category = (string)($_GET['category'] ?? '');
}

$category = strtolower(trim($category));

if ($category !== 'teacher' && $category !== 'student') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid category. Expected teacher or student.']);
    exit;
}

try {
    $pdo = get_pdo();
    $recipients = [];

    if ($category === 'teacher') {
        $stmt = $pdo->query(
            "SELECT doctor_id AS id, full_name AS display_name, email
             FROM doctors
             WHERE email IS NOT NULL AND TRIM(email) <> ''
             ORDER BY full_name ASC"
        );
        foreach ($stmt->fetchAll() as $row) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $recipients[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['display_name'] ?? ''),
                'email' => $email,
            ];
        }
    } else {
        $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
        if ($yearLevel > 0) {
            $stmt = $pdo->prepare(
                "SELECT student_id AS id, full_name AS display_name, email
                 FROM students
                 WHERE email IS NOT NULL AND TRIM(email) <> ''
                   AND year_level = :year_level
                 ORDER BY full_name ASC"
            );
            $stmt->execute([':year_level' => $yearLevel]);
        } else {
            $stmt = $pdo->query(
                "SELECT student_id AS id, full_name AS display_name, email
                 FROM students
                 WHERE email IS NOT NULL AND TRIM(email) <> ''
                 ORDER BY full_name ASC"
            );
        }
        foreach ($stmt->fetchAll() as $row) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $recipients[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['display_name'] ?? ''),
                'email' => $email,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => ['recipients' => $recipients],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

