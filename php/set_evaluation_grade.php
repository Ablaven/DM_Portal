<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';

auth_require_login(true);

function bad_request(string $m): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($courseId <= 0) bad_request('course_id is required.');
    if ($studentId <= 0) bad_request('student_id is required.');

    $scoresRaw = $_POST['scores_json'] ?? '';
    $scoresDecoded = json_decode((string)$scoresRaw, true);
    if (!is_array($scoresDecoded)) bad_request('scores_json must be valid JSON.');

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

    $config = dmportal_eval_fetch_config($pdo, $courseId, $doctorId);
    if (!$config) {
        bad_request('Evaluation config is required before grading.');
    }

    $items = $config['items'] ?? [];
    if (!$items) bad_request('Evaluation config has no items.');

    $itemMap = [];
    foreach ($items as $item) {
        $itemMap[(int)$item['item_id']] = $item;
    }

    $scores = [];
    foreach ($scoresDecoded as $itemId => $score) {
        $id = (int)$itemId;
        if (!$id || !isset($itemMap[$id])) {
            continue;
        }
        if (!is_numeric($score)) bad_request('Scores must be numeric.');
        $num = (float)$score;
        $max = (float)($itemMap[$id]['weight'] ?? 0);
        if ($num < 0 || $num > $max) {
            bad_request('Scores must be between 0 and the assigned mark.');
        }
        $scores[$id] = round($num, 2);
    }

    $attendance = dmportal_eval_compute_attendance($pdo, $courseId, $studentId);
    $attendanceScore = $attendance['score'];
    $finalScore = dmportal_eval_compute_final($items, $scores, $attendanceScore);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO evaluation_grades (course_id, doctor_id, student_id, attendance_score, final_score)'
        . ' VALUES (:course_id, :doctor_id, :student_id, :attendance_score, :final_score)'
        . ' ON DUPLICATE KEY UPDATE attendance_score = VALUES(attendance_score), final_score = VALUES(final_score), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':course_id' => $courseId,
        ':doctor_id' => $doctorId,
        ':student_id' => $studentId,
        ':attendance_score' => $attendanceScore,
        ':final_score' => $finalScore,
    ]);

    $gradeId = (int)$pdo->lastInsertId();
    if ($gradeId === 0) {
        $stmt2 = $pdo->prepare('SELECT grade_id FROM evaluation_grades WHERE course_id = :course_id AND doctor_id = :doctor_id AND student_id = :student_id LIMIT 1');
        $stmt2->execute([':course_id' => $courseId, ':doctor_id' => $doctorId, ':student_id' => $studentId]);
        $gradeId = (int)$stmt2->fetchColumn();
    }

    if ($gradeId > 0) {
        $pdo->prepare('DELETE FROM evaluation_grade_items WHERE grade_id = :grade_id')
            ->execute([':grade_id' => $gradeId]);

        $insert = $pdo->prepare(
            'INSERT INTO evaluation_grade_items (grade_id, item_id, score) VALUES (:grade_id, :item_id, :score)'
        );
        foreach ($scores as $itemId => $score) {
            $insert->execute([
                ':grade_id' => $gradeId,
                ':item_id' => $itemId,
                ':score' => $score,
            ]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'data' => ['saved' => true, 'final_score' => $finalScore, 'attendance_score' => $attendanceScore]]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save evaluation grade.']);
}
