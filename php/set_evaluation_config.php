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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$pdo = null;
try {
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) bad_request('course_id is required.');

    $itemsRaw = $_POST['items_json'] ?? '';
    $decoded = json_decode((string)$itemsRaw, true);
    if (!is_array($decoded)) bad_request('items_json must be valid JSON.');

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

    // Determine which doctor_id to use for the config
    $configDoctorId = 0; // Default: global config (admin/management)
    
    if ($role === 'teacher') {
        // Teachers can only configure their own courses
        if ($userDoctorId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account has no associated doctor ID.']);
            exit;
        }
        
        // Verify the teacher is assigned to this course
        $stmt = $pdo->prepare('SELECT 1 FROM course_doctors WHERE course_id = :course_id AND doctor_id = :doctor_id');
        $stmt->execute([':course_id' => $courseId, ':doctor_id' => $userDoctorId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not assigned to this course.']);
            exit;
        }
        
        // Teachers save config under their doctor_id
        $configDoctorId = $userDoctorId;
    } elseif (!in_array($role, ['admin', 'management'], true)) {
        // Other roles cannot configure
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to configure evaluations.']);
        exit;
    }

    $items = dmportal_eval_normalize_items($pdo, $decoded);
    if (!$items) {
        bad_request('At least one evaluation item is required.');
    }

    $sum = dmportal_eval_items_sum($items);
    if (abs($sum - 100.0) > 0.01) {
        bad_request('Total marks must equal 100.');
    }

    $counts = [];
    foreach ($items as $item) {
        $key = (string)$item['category'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    if (($counts['attendance'] ?? 0) > 1) {
        bad_request('Only one Attendance item is allowed.');
    }
    if (($counts['participation'] ?? 0) > 1) {
        bad_request('Only one Participation item is allowed.');
    }

    $termId = dmportal_get_term_id_from_request($pdo, $_POST);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO evaluation_configs (term_id, course_id, doctor_id) VALUES (:term_id, :course_id, :doctor_id)'
        . ' ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':term_id' => $termId,
        ':course_id' => $courseId,
        ':doctor_id' => $configDoctorId,
    ]);

    $config = dmportal_eval_fetch_config($pdo, $courseId, $configDoctorId, $termId);
    if (!$config) {
        throw new RuntimeException('Failed to load evaluation config after save.');
    }

    $configId = (int)$config['config_id'];

    $pdo->prepare('DELETE FROM evaluation_config_items WHERE config_id = :config_id')
        ->execute([':config_id' => $configId]);

    $insert = $pdo->prepare(
        'INSERT INTO evaluation_config_items (config_id, category_key, item_label, weight, sort_order)'
        . ' VALUES (:config_id, :category_key, :item_label, :weight, :sort_order)'
    );
    $order = 0;
    foreach ($items as $item) {
        $insert->execute([
            ':config_id' => $configId,
            ':category_key' => $item['category'],
            ':item_label' => $item['label'],
            ':weight' => $item['weight'],
            ':sort_order' => $item['sort_order'] ?? $order,
        ]);
        $order++;
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'data' => ['saved' => true, 'term_id' => $termId]]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $message = $e->getMessage();
    echo json_encode([
        'success' => false,
        'error' => $message ? ('Failed to save evaluation config: ' . $message) : 'Failed to save evaluation config.',
    ]);
}
