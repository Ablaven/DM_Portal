<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

auth_require_login(true);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');

try {
    $pdo = get_pdo();

    // Must run before any query — uses static $done guard so safe to call here.
    dmportal_ensure_lecture_materials_table($pdo);

    $hasCourseId = isset($_GET['course_id']) && $_GET['course_id'] !== '';

    // ─────────────────────────────────────────────────────────────────────────
    // Branch A: no course_id → admin / management only (all-materials view)
    // ─────────────────────────────────────────────────────────────────────────
    if (!$hasCourseId) {
        if ($role !== 'admin' && $role !== 'management') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT lm.*,'
            . ' d.full_name AS uploader_name,'
            . ' c.course_name,'
            . ' c.year_level,'
            . ' c.semester'
            . ' FROM lecture_materials lm'
            . ' JOIN doctors d ON d.doctor_id = lm.doctor_id'
            . ' JOIN courses c ON c.course_id = lm.course_id'
            . ' ORDER BY lm.created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Branch B: course_id present → per-course view
    // ─────────────────────────────────────────────────────────────────────────
    $courseId = filter_var($_GET['course_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($courseId === false || $courseId === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid course_id.']);
        exit;
    }
    $courseId = (int)$courseId;

    // Verify course exists
    $courseStmt = $pdo->prepare('SELECT course_id, program, year_level, semester FROM courses WHERE course_id = :course_id LIMIT 1');
    $courseStmt->execute([':course_id' => $courseId]);
    $course = $courseStmt->fetch();
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found.']);
        exit;
    }

    // Role-specific access checks
    if ($role === 'teacher') {
        $doctorId = (int)($u['doctor_id'] ?? 0);
        if ($doctorId <= 0 || !dmportal_is_doctor_assigned_to_course($pdo, $doctorId, $courseId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }
    } elseif ($role === 'student') {
        $studentId = (int)($u['student_id'] ?? 0);
        if ($studentId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }

        // Fetch the student's program / year_level / semester
        $stuStmt = $pdo->prepare(
            'SELECT program, year_level, semester FROM students WHERE student_id = :student_id LIMIT 1'
        );
        $stuStmt->execute([':student_id' => $studentId]);
        $student = $stuStmt->fetch();
        if (!$student) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }

        // The course must match the student's academic context
        if (
            (string)$course['program']    !== (string)$student['program']    ||
            (int)$course['year_level']    !== (int)$student['year_level']    ||
            (int)$course['semester']      !== (int)$student['semester']
        ) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden.']);
            exit;
        }
    }
    // admin / management: no additional check needed

    // Fetch materials for the course
    $stmt = $pdo->prepare(
        'SELECT lm.*, d.full_name AS uploader_name'
        . ' FROM lecture_materials lm'
        . ' JOIN doctors d ON d.doctor_id = lm.doctor_id'
        . ' WHERE lm.course_id = :course_id'
        . ' ORDER BY lm.created_at DESC'
    );
    $stmt->execute([':course_id' => $courseId]);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
