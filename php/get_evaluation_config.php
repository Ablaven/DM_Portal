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

    if (!in_array($role, ['admin', 'management'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Configuration access is restricted to admins.']);
        exit;
    }

    $config = dmportal_eval_fetch_config($pdo, $courseId, 0);
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
            'doctor_id' => $doctorId,
            'items' => $items,
            'categories' => $categories,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch evaluation config.']);
}
