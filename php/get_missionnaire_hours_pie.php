<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_doctor_schema_helpers.php';

// Dashboard widget: show course-hours distribution by doctor.
// Uses courses.total_hours + course_doctors assignment.
// If course_doctor_hours allocations exist for a course, those allocations are used (missing rows count as 0).
// If no allocations exist for a course, the course total_hours are split evenly across assigned doctors.

auth_require_roles(['admin','management'], true);

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

    $touchTable = function (string $table) use ($pdo): bool {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($stmt) $stmt->fetch(PDO::FETCH_NUM);
            if ($stmt) $stmt->closeCursor();
            return true;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1146) return false;
            throw $e;
        }
    };

    if (!$touchTable('doctors') || !$touchTable('courses')) {
        echo json_encode(['success' => true, 'data' => ['egyptian' => null, 'french' => null, 'doctors' => []]]);
        exit;
    }

    dmportal_ensure_doctor_type_column($pdo);

    $hasCourseDoctors = $touchTable('course_doctors');
    $hasCourseDoctorHours = $touchTable('course_doctor_hours');

    // Aggregate by doctor_type (Egyptian vs French).

    $params = [];
    $where = [];
    if ($yearLevel > 0) {
        $where[] = 'c.year_level = :year_level';
        $params[':year_level'] = $yearLevel;
    }
    if ($semester > 0) {
        $where[] = 'c.semester = :semester';
        $params[':semester'] = $semester;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $rows = [];

    if ($hasCourseDoctors) {
        $hJoin = $hasCourseDoctorHours
            ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c.course_id AND h.doctor_id = cd.doctor_id'
            : 'LEFT JOIN (SELECT NULL AS course_id, NULL AS doctor_id, NULL AS allocated_hours) h ON 1=0';

        // alloc presence per course (any split hours at all?)
        $allocJoin = $hasCourseDoctorHours
            ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
            : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

        $sql = "
            SELECT
              d.doctor_id,
              d.full_name,
              d.doctor_type,
              ROUND(SUM(
                CASE
                  WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
                  ELSE (COALESCE(c.total_hours, 0) / GREATEST(cnt.cnt, 1))
                END
              ), 2) AS total_hours
            FROM course_doctors cd
            JOIN courses c ON c.course_id = cd.course_id
            JOIN doctors d ON d.doctor_id = cd.doctor_id
            JOIN (
              SELECT course_id, COUNT(*) AS cnt
              FROM course_doctors
              GROUP BY course_id
            ) cnt ON cnt.course_id = c.course_id
            $hJoin
            $allocJoin
            $whereSql
            GROUP BY d.doctor_id, d.full_name, d.doctor_type
            HAVING total_hours > 0
            ORDER BY total_hours DESC, d.full_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } else {
        // Legacy fallback: courses.doctor_id
        $sql = "
            SELECT
              d.doctor_id,
              d.full_name,
              d.doctor_type,
              ROUND(SUM(COALESCE(c.total_hours, 0)), 2) AS total_hours
            FROM courses c
            JOIN doctors d ON d.doctor_id = c.doctor_id
            $whereSql
              " . ($whereSql ? ' AND' : 'WHERE') . " c.doctor_id IS NOT NULL
            GROUP BY d.doctor_id, d.full_name, d.doctor_type
            HAVING total_hours > 0
            ORDER BY total_hours DESC, d.full_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    $doctors = [];
    $egyptianTotal = 0.0;
    $frenchTotal = 0.0;

    foreach ($rows as $r) {
        $docId = (int)($r['doctor_id'] ?? 0);
        $name = (string)($r['full_name'] ?? '');
        $total = (float)($r['total_hours'] ?? 0);
        $type = ucfirst(strtolower((string)($r['doctor_type'] ?? 'Egyptian')));
        if (!in_array($type, ['Egyptian', 'French'], true)) {
            $type = 'Egyptian';
        }

        if ($type === 'French') $frenchTotal += $total;
        else $egyptianTotal += $total;

        $doctors[] = [
            'doctor_id' => $docId,
            'full_name' => $name,
            'total_hours' => round($total, 2),
            'doctor_type' => $type,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'egyptian' => [
                'label' => 'Egyptian',
                'total_hours' => round($egyptianTotal, 2),
            ],
            'french' => [
                'label' => 'French',
                'total_hours' => round($frenchTotal, 2),
            ],
            'doctors' => $doctors,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to compute Missionnaire pie chart data.',
        // 'debug' => $e->getMessage(),
    ]);
}
