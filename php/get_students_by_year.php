<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin', 'management'], true);

header('Content-Type: application/json');

try {
    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;

    if ($yearLevel <= 0) {
        echo json_encode(['success' => false, 'error' => 'year_level is required']);
        exit;
    }

    $pdo = get_pdo();

    $stmt = $pdo->prepare('
        SELECT student_id, full_name, student_code, year_level
        FROM students
        WHERE year_level = :year
        ORDER BY full_name ASC
    ');
    $stmt->execute([':year' => $yearLevel]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load students: ' . $e->getMessage()
    ]);
}
