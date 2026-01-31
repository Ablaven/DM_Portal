<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);
require_once __DIR__ . '/_students_schema_helpers.php';

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
$studentCode = trim((string)($_POST['student_code'] ?? ''));

$program = normalize_program((string)($_POST['program'] ?? ''));
$yearLevel = normalize_year_level((int)($_POST['year_level'] ?? 0));
// students apply to both semesters
$semester = 0;

if ($fullName === '') bad_request('full_name is required.');
if ($email === '') bad_request('email is required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bad_request('email is invalid.');
if ($studentCode === '') bad_request('student_code is required.');

try {
    $pdo = get_pdo();

    if ($yearLevel <= 0) bad_request('year_level must be 1-3.');

    $fields = ['full_name', 'email', 'student_code', 'program', 'year_level', 'semester'];
    $params = [
        ':full_name' => $fullName,
        ':email' => $email,
        ':student_code' => $studentCode,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => 0,
    ];

    $placeholders = array_map(fn($f) => ':' . $f, $fields);

    $ph = array_map(fn($f) => ':' . $f, $fields);
    $sql = 'INSERT INTO students (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $ph) . ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => ['student_id' => (int)$pdo->lastInsertId()]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('A student with this email or ID already exists.');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add student.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add student.']);
}
