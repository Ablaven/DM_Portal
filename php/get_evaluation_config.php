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
    $userDoctorId = (int)($u['doctor_id'] ?? 0);
    // Determine which config to fetch
    if ($role === 'teacher') {
        if ($userDoctorId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account has no associated doctor ID.']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM course_doctors WHERE course_id = :course_id AND doctor_id = :doctor_id');
        $stmt->execute([':course_id' => $courseId, ':doctor_id' => $userDoctorId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not assigned to this course.']);
            exit;
        }
        $configDoctorId = $userDoctorId;
    } elseif (in_array($role, ['admin', 'management'], true)) {
        // Admin can view any teacher's config by passing doctor_id in the request
        $requestedDoctorId = (int)($_GET['doctor_id'] ?? 0);
        if ($requestedDoctorId > 0) {
            $stmt = $pdo->prepare('SELECT 1 FROM course_doctors WHERE course_id = :course_id AND doctor_id = :doctor_id');
            $stmt->execute([':course_id' => $courseId, ':doctor_id' => $requestedDoctorId]);
            $configDoctorId = $stmt->fetch() ? $requestedDoctorId : 0;
        } else {
            $configDoctorId = 0;
        }
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to view evaluation configuration.']);
        exit;
    }
    $termId = dmportal_get_term_id_from_request($pdo, $_GET);
    $config = dmportal_eval_fetch_config($pdo, $courseId, $configDoctorId, $termId);
    $items = $config['items'] ?? [];
    $catStmt = $pdo->query('SELECT category_key, label FROM evaluation_categories ORDER BY sort_order ASC, category_key ASC');
    $categories = $catStmt->fetchAll();
    echo json_encode([
        'success' => true,
        'data' => [
            'course' => [
                'course_id' => (int)$course['course_id'],
                'course_name' => (string)$course['course_name'],
                'year_level' => (int)$course['year_level'],
                'semester' => (int)$course['semester'],
            ],
            'doctor_id' => $userDoctorId,
            'config_doctor_id' => $configDoctorId,
            'term_id' => $termId,
            'items' => $items,
            'categories' => $categories,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation config.']);
}