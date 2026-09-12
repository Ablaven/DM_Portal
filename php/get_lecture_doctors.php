<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

auth_require_login(true);

// Validate required GET parameters.
$yearLevelRaw = $_GET['year_level'] ?? null;
$semesterRaw  = $_GET['semester']   ?? null;
$program      = isset($_GET['program']) ? trim((string)$_GET['program']) : null;

if ($yearLevelRaw === null || $semesterRaw === null || $program === null || $program === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing required parameters: year_level, semester, program.',
    ]);
    exit;
}

$yearLevel = (int)$yearLevelRaw;
$semester  = (int)$semesterRaw;

if ($yearLevel <= 0 || $semester <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'year_level and semester must be positive integers.',
    ]);
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_lecture_materials_table($pdo);

    // Fetch distinct doctors who have at least one course matching the student's context.
    // Checks both many-to-many course_doctors and legacy courses.doctor_id column.
    $doctorStmt = $pdo->prepare(
        'SELECT DISTINCT d.doctor_id, d.full_name'
        . ' FROM doctors d'
        . ' JOIN ('
        . '     SELECT doctor_id, course_id FROM course_doctors'
        . '     UNION'
        . '     SELECT doctor_id, course_id FROM courses WHERE doctor_id IS NOT NULL'
        . ' ) dc ON dc.doctor_id = d.doctor_id'
        . ' JOIN courses c ON c.course_id = dc.course_id'
        . ' WHERE c.program = :program'
        . '   AND c.year_level = :year_level'
        . '   AND c.semester = :semester'
        . ' ORDER BY d.full_name ASC'
    );
    $doctorStmt->execute([
        ':program'    => $program,
        ':year_level' => $yearLevel,
        ':semester'   => $semester,
    ]);
    $doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($doctors)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Collect doctor IDs for the second query.
    $doctorIds = array_map(fn($d) => (int)$d['doctor_id'], $doctors);

    // Build a safe IN(...) placeholder list.
    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));

    // Fetch courses per doctor, filtered to the same context.
    // UNION covers both assignment models, same as the doctor query above.
    $courseStmt = $pdo->prepare(
        'SELECT DISTINCT c.course_id, c.course_name, c.subject_code, dc.doctor_id'
        . ' FROM courses c'
        . ' JOIN ('
        . '     SELECT doctor_id, course_id FROM course_doctors'
        . '     UNION'
        . '     SELECT doctor_id, course_id FROM courses WHERE doctor_id IS NOT NULL'
        . ' ) dc ON dc.course_id = c.course_id'
        . ' WHERE dc.doctor_id IN (' . $placeholders . ')'
        . '   AND c.program = ?'
        . '   AND c.year_level = ?'
        . '   AND c.semester = ?'
        . ' ORDER BY c.course_name ASC'
    );

    // Positional parameters: first all doctor IDs, then the context filters.
    $courseParams = array_merge($doctorIds, [$program, $yearLevel, $semester]);
    $courseStmt->execute($courseParams);
    $courseRows = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group courses by doctor_id.
    $coursesByDoctor = [];
    foreach ($courseRows as $row) {
        $did = (int)$row['doctor_id'];
        $coursesByDoctor[$did][] = [
            'course_id'    => (int)$row['course_id'],
            'course_name'  => (string)$row['course_name'],
            'subject_code' => (string)($row['subject_code'] ?? ''),
        ];
    }

    // Assemble the final response.
    $data = [];
    foreach ($doctors as $doc) {
        $did = (int)$doc['doctor_id'];
        $data[] = [
            'doctor_id' => $did,
            'full_name' => (string)$doc['full_name'],
            'courses'   => $coursesByDoctor[$did] ?? [],
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error.',
    ]);
}
