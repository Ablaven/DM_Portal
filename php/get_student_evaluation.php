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
    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $studentId = (int)($u['student_id'] ?? 0);

    $isAdmin = ($role === 'admin' || $role === 'management');
    $scope = 'self';

    if ($isAdmin) {
        $scope = (string)($_GET['scope'] ?? 'all');
    }

    if (!$isAdmin) {
        if ($role !== 'student') {
            auth_require_roles(['student'], true);
        }
        if ($studentId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Student account missing student_id.']);
            exit;
        }
    }

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $targetStudentId = $studentId;
    if ($isAdmin && isset($_GET['student_id'])) {
        $targetStudentId = (int)$_GET['student_id'];
        $scope = 'student';
    }

    $yearLevel = (int)($_GET['year_level'] ?? 0);
    $semester = (int)($_GET['semester'] ?? 0);
    $courseId = (int)($_GET['course_id'] ?? 0);
    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    $where = [];
    $params = [];

    if (!$isAdmin || $scope !== 'all') {
        $where[] = 'g.student_id = :student_id';
        $params[':student_id'] = $targetStudentId;
    }

    if ($yearLevel > 0) {
        $where[] = 'c.year_level = :year_level';
        $params[':year_level'] = $yearLevel;
    }

    if ($semester > 0) {
        $where[] = 'c.semester = :semester';
        $params[':semester'] = $semester;
    }

    if ($courseId > 0) {
        $where[] = 'c.course_id = :course_id';
        $params[':course_id'] = $courseId;
    }

    if ($termId > 0) {
        $where[] = 'g.term_id = :term_id';
        $params[':term_id'] = $termId;
    }

    $sql =
        "SELECT g.grade_id, g.student_id, g.attendance_score, g.final_score,
                c.course_id, c.course_name, c.year_level, c.semester,
                s.full_name AS student_name, s.student_code
         FROM evaluation_grades g
         JOIN courses c ON c.course_id = g.course_id
         JOIN students s ON s.student_id = g.student_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY s.full_name ASC, c.year_level ASC, c.course_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $gradeIds = array_map(fn($r) => (int)$r['grade_id'], $rows);
    $itemsByGrade = [];

    if ($gradeIds) {
        $in = implode(',', array_fill(0, count($gradeIds), '?'));
        $itemsStmt = $pdo->prepare(
            "SELECT gi.grade_id, ci.category_key, ci.item_label, ci.weight, gi.score
             FROM evaluation_grade_items gi
             JOIN evaluation_config_items ci ON ci.item_id = gi.item_id
             WHERE gi.grade_id IN ($in)"
        );
        $itemsStmt->execute($gradeIds);
        $itemRows = $itemsStmt->fetchAll();

        foreach ($itemRows as $r) {
            $gid = (int)$r['grade_id'];
            if (!isset($itemsByGrade[$gid])) {
                $itemsByGrade[$gid] = [];
            }
            $itemsByGrade[$gid][] = [
                'category' => (string)$r['category_key'],
                'label' => (string)$r['item_label'],
                'weight' => (float)$r['weight'],
                'score' => (float)$r['score'],
            ];
        }
    }

    $items = [];
    foreach ($rows as $r) {
        $gid = (int)$r['grade_id'];
        $items[] = [
            'grade_id' => $gid,
            'course_id' => (int)$r['course_id'],
            'course_name' => (string)$r['course_name'],
            'year_level' => (int)$r['year_level'],
            'semester' => (int)$r['semester'],
            'student_id' => (int)$r['student_id'],
            'student_name' => (string)$r['student_name'],
            'student_code' => (string)($r['student_code'] ?? ''),
            'attendance_score' => $r['attendance_score'] !== null ? (float)$r['attendance_score'] : null,
            'final_score' => $r['final_score'] !== null ? (float)$r['final_score'] : null,
            'items' => $itemsByGrade[$gid] ?? [],
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'scope' => $isAdmin ? $scope : 'self',
            'term_id' => $termId,
            'items' => $items,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch student evaluation.']);
}
