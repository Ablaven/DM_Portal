<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

// ─── Auth ─────────────────────────────────────────────────────────────────────
auth_require_roles(['admin', 'management'], true);

// ─── Method guard ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ─── Input parsing: POST body first, fall back to JSON body ──────────────────
$materialId     = 0;
$targetCourseId = 0;

if (isset($_POST['material_id']) || isset($_POST['target_course_id'])) {
    $materialId     = (int)($_POST['material_id']     ?? 0);
    $targetCourseId = (int)($_POST['target_course_id'] ?? 0);
} else {
    $raw = (string)file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $materialId     = (int)($decoded['material_id']     ?? 0);
            $targetCourseId = (int)($decoded['target_course_id'] ?? 0);
        }
    }
}

// ─── Validate material_id ─────────────────────────────────────────────────────
if ($materialId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid material_id.']);
    exit;
}

// ─── Validate target_course_id ────────────────────────────────────────────────
if ($targetCourseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid target_course_id.']);
    exit;
}

try {
    $pdo = get_pdo();

    // MUST be called before beginTransaction() — DDL causes implicit commit in MySQL.
    dmportal_ensure_lecture_materials_table($pdo);

    // ─── Fetch material (before transaction) ──────────────────────────────────
    $material = dmportal_get_material_by_id($pdo, $materialId);
    if ($material === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found.']);
        exit;
    }

    $sourceCourseId = (int)$material['course_id'];

    // ─── Verify target course exists ──────────────────────────────────────────
    $stmtCourse = $pdo->prepare('SELECT 1 FROM courses WHERE course_id = :course_id LIMIT 1');
    $stmtCourse->execute([':course_id' => $targetCourseId]);
    if ($stmtCourse->fetchColumn() === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Target course not found.']);
        exit;
    }

    // ─── Guard: same course ───────────────────────────────────────────────────
    if ($sourceCourseId === $targetCourseId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Material is already assigned to this course.']);
        exit;
    }

    // ─── Resolve filesystem paths ─────────────────────────────────────────────
    $storedFilename = (string)$material['stored_filename'];
    $oldPath        = dmportal_get_stored_file_path($sourceCourseId, $storedFilename);
    $newPath        = dmportal_get_stored_file_path($targetCourseId, $storedFilename);

    // ─── Transactional move ───────────────────────────────────────────────────
    $pdo->beginTransaction();

    // Step 1: Update DB record
    $stmtUpdate = $pdo->prepare(
        'UPDATE lecture_materials SET course_id = :target_course_id WHERE material_id = :material_id'
    );
    $stmtUpdate->execute([
        ':target_course_id' => $targetCourseId,
        ':material_id'      => $materialId,
    ]);

    // Step 2: Ensure target upload directory exists
    if (!dmportal_ensure_upload_dir($targetCourseId)) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to create upload directory.']);
        exit;
    }

    // Step 3: Move file on filesystem
    if (!@rename($oldPath, $newPath)) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to move file.']);
        exit;
    }

    // Step 4: Commit
    $pdo->commit();

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
