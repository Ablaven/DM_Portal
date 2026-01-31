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

$studentId = (int)($_POST['student_id'] ?? 0);
$fullName = trim((string)($_POST['full_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$studentCode = trim((string)($_POST['student_code'] ?? ''));
$program = normalize_program((string)($_POST['program'] ?? ''));
$yearLevel = normalize_year_level((int)($_POST['year_level'] ?? 0));
// students apply to both semesters
$semester = 0;

if ($studentId <= 0) bad_request('student_id is required.');
if ($fullName === '') bad_request('full_name is required.');
if ($email === '') bad_request('email is required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bad_request('email is invalid.');
if ($studentCode === '') bad_request('student_code is required.');

try {
    $pdo = get_pdo();

    // New schema expects students(student_code, program, year_level, semester)

    $chk = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id');
    $chk->execute([':id' => $studentId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found.']);
        exit;
    }

    if ($yearLevel <= 0) bad_request('year_level must be 1-3.');

    $sets = [
        'full_name = :full_name',
        'email = :email',
        'student_code = :student_code',
        'program = :program',
        'year_level = :year_level',
        'semester = :semester'
    ];

    $params = [
        ':full_name' => $fullName,
        ':email' => $email,
        ':student_code' => $studentCode,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':semester' => 0,
        ':id' => $studentId,
    ];

    $sql = 'UPDATE students SET ' . implode(', ', $sets) . ' WHERE student_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => ['updated' => true]]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        bad_request('A student with this email or ID already exists.');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update student.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update student.']);
}
