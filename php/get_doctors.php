<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_login(true);

try {
    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorFilterId = null;
    if ($role === 'teacher') {
        $did = (int)($u['doctor_id'] ?? 0);
        if ($did <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher account is missing doctor_id.']);
            exit;
        }
        $doctorFilterId = $did;
    }

    $pdo = get_pdo();

    try {
        if ($doctorFilterId !== null) {
            $stmt = $pdo->prepare('SELECT doctor_id, full_name, email, phone_number, color_code FROM doctors WHERE doctor_id = :id LIMIT 1');
            $stmt->execute([':id' => $doctorFilterId]);
        } else {
            $stmt = $pdo->query('SELECT doctor_id, full_name, email, phone_number, color_code FROM doctors ORDER BY full_name ASC');
        }
        $doctors = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Backward compatibility: older DBs may not have doctors.phone_number yet.
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            if ($doctorFilterId !== null) {
                $stmt = $pdo->prepare('SELECT doctor_id, full_name, email, color_code FROM doctors WHERE doctor_id = :id LIMIT 1');
                $stmt->execute([':id' => $doctorFilterId]);
            } else {
                $stmt = $pdo->query('SELECT doctor_id, full_name, email, color_code FROM doctors ORDER BY full_name ASC');
            }
            $doctors = $stmt->fetchAll();
            // Ensure the JSON always contains phone_number for frontend consistency.
            foreach ($doctors as &$d) {
                if (!array_key_exists('phone_number', $d)) $d['phone_number'] = null;
            }
            unset($d);
        } else {
            throw $e;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $doctors,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch doctors.',
        // 'debug' => $e->getMessage(),
    ]);
}
