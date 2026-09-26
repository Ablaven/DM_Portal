<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

auth_require_login(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Resolve material_id from POST body or JSON body ─────────────────────────
$materialId = 0;

if (isset($_POST['material_id'])) {
    $materialId = (int)$_POST['material_id'];
} else {
    $raw = (string)file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['material_id'])) {
            $materialId = (int)$decoded['material_id'];
        }
    }
}

if ($materialId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid material_id.']);
    exit;
}

try {
    $pdo = get_pdo();

    // ── Ensure schema exists (best practice per spec notes) ─────────────────
    dmportal_ensure_lecture_materials_table($pdo);

    // ── Fetch the material record ────────────────────────────────────────────
    $material = dmportal_get_material_by_id($pdo, $materialId);

    if ($material === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found.']);
        exit;
    }

    // ── Ownership check for teacher role ────────────────────────────────────
    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');

    if ($role === 'teacher') {
        $ownDoctorId = (int)($u['doctor_id'] ?? 0);
        $materialDoctorId = (int)$material['doctor_id'];

        if ($ownDoctorId <= 0 || $ownDoctorId !== $materialDoctorId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Cannot delete another doctor\'s file.']);
            exit;
        }
    } elseif ($role !== 'admin' && $role !== 'management') {
        // Any other role (e.g. student) is not permitted to delete
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }

    // ── Delete DB row first (no filesystem action on failure) ────────────────
    $courseId       = (int)$material['course_id'];
    $storedFilename = (string)$material['stored_filename'];

    $stmt = $pdo->prepare('DELETE FROM lecture_materials WHERE material_id = :material_id');
    $stmt->execute([':material_id' => $materialId]);

    if ($stmt->rowCount() === 0) {
        // Row was already gone - treat as 404
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found.']);
        exit;
    }

    // ── Remove stored file from filesystem ───────────────────────────────────
    $filePath = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);

    if (file_exists($filePath)) {
        if (!@unlink($filePath)) {
            error_log(
                'delete_lecture_material: failed to unlink file '
                . $filePath
                . ' for material_id=' . $materialId
            );
            // Log error but still return success - DB row is already deleted
        }
    }

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete material record.']);
}
