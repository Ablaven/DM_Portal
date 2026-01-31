<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

// Returns students filtered by program + year_level.
// Students apply to both semesters (semester=0), so we do not filter by semester.

try {
    $pdo = get_pdo();

    $program = trim((string)($_GET['program'] ?? ''));
    $yearLevel = (int)($_GET['year_level'] ?? 0);
    $semester = (int)($_GET['semester'] ?? 0);

    $select = [
        'student_id',
        'full_name',
        'email',
        'student_code',
        'program',
        'year_level',
        'semester'
    ];

    $where = [];
    $params = [];

    if ($program !== '') {
        $where[] = 'program = :program';
        $params[':program'] = $program;
    }

    if ($yearLevel > 0) {
        $where[] = 'year_level = :year_level';
        $params[':year_level'] = $yearLevel;
    }

    // Students apply to BOTH semesters. If a students.semester column exists,
    // we treat 0 as "all" and do not filter by semester here.
    // (Filtering should be driven by courses, not students.)

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM students';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY full_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch students.']);
}
