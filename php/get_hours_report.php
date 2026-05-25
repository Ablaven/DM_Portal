<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_hours_report_helpers.php';

// Admin/teacher report. Teachers are scoped to their own doctor_id.
auth_require_roles(['admin', 'teacher'], true);

$user = auth_current_user();
$role = (string)($user['role'] ?? '');
$doctorScopeId = 0;
if ($role === 'teacher') {
    $doctorScopeId = (int)($user['doctor_id'] ?? 0);
    if ($doctorScopeId <= 0) {
        echo json_encode(['success' => true, 'data' => ['doctors' => []]]);
        exit;
    }
}

try {
    $pdo = get_pdo();

    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'year_level must be 1-3 or empty.']);
        exit;
    }
    if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'semester must be 1-2 or empty.']);
        exit;
    }

    $doctors = dmportal_fetch_hours_report($pdo, $yearLevel, $semester, $doctorScopeId, 0);

    echo json_encode(['success' => true, 'data' => ['doctors' => $doctors]]);
} catch (Throwable $e) {
    http_response_code(500);
    $debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

    $debugInfo = null;
    if ($debug) {
        $debugInfo = [
            'message' => $e->getMessage(),
            'type' => get_class($e),
        ];
        if ($e instanceof PDOException) {
            $debugInfo['sqlstate'] = $e->getCode();
            $debugInfo['errorInfo'] = $e->errorInfo ?? null;
        }
    }

    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch hours report.',
        'debug' => $debugInfo,
    ]);
}
