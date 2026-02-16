<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

try {
    $courseId = (int)($_GET['course_id'] ?? 0);
    if ($courseId <= 0) bad_request('course_id is required.');

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $course = dmportal_eval_load_course($pdo, $courseId);
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found.']);
        exit;
    }

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);

    if ($role === 'teacher') {
        if (!dmportal_eval_can_doctor_access_course($pdo, $doctorId, $courseId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }
    }

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    $configDoctorId = $role === 'teacher' ? 0 : $doctorId;
    $config = dmportal_eval_fetch_config($pdo, $courseId, $configDoctorId, $termId);
    $items = $config['items'] ?? [];

    $studentsStmt = $pdo->prepare(
        'SELECT student_id, full_name, student_code
         FROM students
         WHERE year_level = :year_level
         ORDER BY full_name ASC'
    );
    $studentsStmt->execute([':year_level' => (int)$course['year_level']]);
    $students = $studentsStmt->fetchAll();

    $gradesStmt = $pdo->prepare(
        'SELECT grade_id, student_id, attendance_score, final_score
         FROM evaluation_grades
         WHERE course_id = :course_id AND doctor_id = :doctor_id AND term_id = :term_id'
    );
    $gradesStmt->execute([':course_id' => $courseId, ':doctor_id' => $doctorId, ':term_id' => $termId]);
    $gradeRows = $gradesStmt->fetchAll();
    $gradeMap = [];
    foreach ($gradeRows as $r) {
        $gradeMap[(string)$r['student_id']] = $r;
    }

    $itemScoresStmt = $pdo->prepare(
        'SELECT gi.grade_id, gi.item_id, gi.score
         FROM evaluation_grade_items gi
         JOIN evaluation_grades g ON g.grade_id = gi.grade_id
         WHERE g.course_id = :course_id AND g.doctor_id = :doctor_id AND g.term_id = :term_id'
    );
    $itemScoresStmt->execute([':course_id' => $courseId, ':doctor_id' => $doctorId, ':term_id' => $termId]);
    $itemScoreRows = $itemScoresStmt->fetchAll();
    $scoreMap = [];
    foreach ($itemScoreRows as $r) {
        $gid = (int)$r['grade_id'];
        if (!isset($scoreMap[$gid])) {
            $scoreMap[$gid] = [];
        }
        $scoreMap[$gid][(int)$r['item_id']] = (float)$r['score'];
    }

    $itemsOut = [];
    foreach ($items as $item) {
        $itemsOut[] = [
            'item_id' => (int)$item['item_id'],
            'category' => (string)$item['category_key'],
            'label' => (string)$item['item_label'],
            'weight' => (float)$item['weight'],
        ];
    }
    $attendanceMax = dmportal_eval_get_attendance_weight($itemsOut);

    $itemsPayload = [];
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $existing = $gradeMap[(string)$sid] ?? null;
        $gradeId = $existing ? (int)$existing['grade_id'] : 0;
        $scores = $gradeId ? ($scoreMap[$gradeId] ?? []) : [];

        $attendance = dmportal_eval_compute_attendance($pdo, $courseId, $sid, $attendanceMax, $termId);
        $attendanceScore = $attendance['score'];
        $finalScore = null;
        if ($items) {
            $finalScore = dmportal_eval_compute_final($items, $scores, $attendanceScore);
        }

        $itemsPayload[] = [
            'student_id' => $sid,
            'full_name' => (string)$s['full_name'],
            'student_code' => (string)($s['student_code'] ?? ''),
            'attendance' => $attendance,
            'scores' => $scores,
            'stored_final_score' => $existing['final_score'] ?? null,
            'computed_final_score' => $finalScore,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'course' => [
                'course_id' => (int)$course['course_id'],
                'course_name' => (string)$course['course_name'],
                'year_level' => (int)$course['year_level'],
                'semester' => (int)$course['semester'],
            ],
            'doctor_id' => $doctorId,
            'term_id' => $termId,
            'items' => $itemsOut,
            'students' => $itemsPayload,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation grades.']);
}
