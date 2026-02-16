<?php

declare(strict_types=1);

/**
 * Evaluation system schema helper.
 * Tables:
 * - evaluation_configs (per course + doctor)
 * - evaluation_config_items (per config, itemized parameters)
 * - evaluation_grades (per course + doctor + student)
 * - evaluation_grade_items (per grade, item scores)
 */
function dmportal_ensure_evaluation_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evaluation_categories (\n"
        ."  category_key VARCHAR(40) NOT NULL,\n"
        ."  label VARCHAR(120) NOT NULL,\n"
        ."  sort_order INT NOT NULL DEFAULT 0,\n"
        ."  PRIMARY KEY (category_key)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evaluation_configs (\n"
        ."  config_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,\n"
        ."  course_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (config_id),\n"
        ."  UNIQUE KEY uq_eval_config (term_id, course_id, doctor_id),\n"
        ."  KEY idx_eval_config_term (term_id),\n"
        ."  KEY idx_eval_config_course (course_id),\n"
        ."  KEY idx_eval_config_doctor (doctor_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evaluation_config_items (\n"
        ."  item_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  config_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  category_key VARCHAR(40) NOT NULL,\n"
        ."  item_label VARCHAR(120) NOT NULL,\n"
        ."  weight DECIMAL(6,2) NOT NULL DEFAULT 0,\n"
        ."  sort_order INT NOT NULL DEFAULT 0,\n"
        ."  PRIMARY KEY (item_id),\n"
        ."  KEY idx_eval_item_config (config_id),\n"
        ."  KEY idx_eval_item_category (category_key)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evaluation_grades (\n"
        ."  grade_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,\n"
        ."  course_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  doctor_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  student_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  attendance_score DECIMAL(5,2) DEFAULT NULL,\n"
        ."  final_score DECIMAL(5,2) DEFAULT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (grade_id),\n"
        ."  UNIQUE KEY uq_eval_grade (term_id, course_id, doctor_id, student_id),\n"
        ."  KEY idx_eval_grade_term (term_id),\n"
        ."  KEY idx_eval_grade_course (course_id),\n"
        ."  KEY idx_eval_grade_doctor (doctor_id),\n"
        ."  KEY idx_eval_grade_student (student_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evaluation_grade_items (\n"
        ."  grade_item_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  grade_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  item_id BIGINT(20) UNSIGNED NOT NULL,\n"
        ."  score DECIMAL(5,2) NOT NULL DEFAULT 0,\n"
        ."  PRIMARY KEY (grade_item_id),\n"
        ."  UNIQUE KEY uq_eval_grade_item (grade_id, item_id),\n"
        ."  KEY idx_eval_grade_item_grade (grade_id),\n"
        ."  KEY idx_eval_grade_item_item (item_id)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dmportal_eval_categories(): array
{
    return [
        'attendance' => 'Attendance',
        'participation' => 'Participation',
        'projects' => 'Projects',
        'quizzes' => 'Quizzes',
        'exams' => 'Exams',
        'presentations' => 'Presentations',
        'assignments' => 'Assignments',
    ];
}

function dmportal_eval_seed_categories(PDO $pdo): void
{
    $defaults = dmportal_eval_categories();
    $stmt = $pdo->prepare('INSERT INTO evaluation_categories (category_key, label, sort_order) VALUES (:key, :label, :sort) ON DUPLICATE KEY UPDATE label = VALUES(label)');
    $order = 0;
    foreach ($defaults as $key => $label) {
        $stmt->execute([':key' => $key, ':label' => $label, ':sort' => $order]);
        $order++;
    }
}

function dmportal_eval_normalize_items(PDO $pdo, array $items): array
{
    $defaults = dmportal_eval_categories();
    $allowed = array_keys($defaults);

    try {
        $stmt = $pdo->query('SELECT category_key FROM evaluation_categories');
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $allowed[] = (string)$r['category_key'];
        }
        $allowed = array_unique($allowed);
    } catch (Throwable $e) {
        // ignore, fallback to defaults
    }

    $allowedMap = array_fill_keys($allowed, true);
    $clean = [];
    $order = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $category = (string)($item['category'] ?? '');
        if (!isset($allowedMap[$category])) {
            continue;
        }
        $label = trim((string)($item['label'] ?? ''));
        if ($category === 'attendance') {
            $label = 'Attendance';
        }
        if ($label === '') {
            continue;
        }
        $weight = is_numeric($item['weight'] ?? null) ? (float)$item['weight'] : 0.0;
        if ($weight <= 0) {
            continue;
        }
        $clean[] = [
            'category' => $category,
            'label' => $label,
            'weight' => round($weight, 2),
            'sort_order' => (int)($item['sort_order'] ?? $order),
        ];
        $order++;
    }
    return $clean;
}

function dmportal_eval_items_sum(array $items): float
{
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += (float)($item['weight'] ?? 0);
    }
    return round($sum, 2);
}

function dmportal_eval_get_attendance_weight(array $items, float $default = 20.0): float
{
    foreach ($items as $item) {
        $category = (string)($item['category_key'] ?? $item['category'] ?? '');
        if ($category === 'attendance') {
            $weight = (float)($item['weight'] ?? 0);
            return $weight > 0 ? $weight : $default;
        }
    }
    return $default;
}

function dmportal_eval_compute_attendance(PDO $pdo, int $courseId, int $studentId, float $maxScore = 20.0, ?int $termId = null): array
{
    $params = [':course_id' => $courseId];
    $termClause = '';
    if ($termId !== null && $termId > 0) {
        $termClause = ' AND w.term_id = :term_id';
        $params[':term_id'] = $termId;
    }

    $totalStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT s.schedule_id)
         FROM doctor_schedules s
         JOIN weeks w ON w.week_id = s.week_id
         WHERE s.course_id = :course_id{$termClause}"
    );
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    $presentParams = [':course_id' => $courseId, ':student_id' => $studentId];
    if ($termId !== null && $termId > 0) {
        $presentParams[':term_id'] = $termId;
    }

    $presentStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM attendance_records ar
         JOIN doctor_schedules s ON s.schedule_id = ar.schedule_id
         JOIN weeks w ON w.week_id = s.week_id
         WHERE s.course_id = :course_id
           AND ar.student_id = :student_id
           AND UPPER(ar.status) = 'PRESENT'"
           . ($termId !== null && $termId > 0 ? ' AND ar.term_id = :term_id' : '')
    );
    $presentStmt->execute($presentParams);
    $present = (int)$presentStmt->fetchColumn();

    $score = $total > 0 ? round(($present / $total) * $maxScore, 2) : 0.0;

    return [
        'total' => $total,
        'present' => $present,
        'score' => $score,
        'max_score' => $maxScore,
    ];
}

function dmportal_eval_compute_final(array $items, array $scores, float $attendanceScore): float
{
    $sum = 0.0;
    foreach ($items as $item) {
        $mark = (float)($item['weight'] ?? 0);
        $itemId = (int)($item['item_id'] ?? 0);
        $category = (string)($item['category_key'] ?? $item['category'] ?? '');

        if ($category === 'attendance') {
            $sum += min($attendanceScore, $mark);
            continue;
        }
        $val = 0.0;
        if ($itemId && isset($scores[$itemId]) && is_numeric($scores[$itemId])) {
            $val = (float)$scores[$itemId];
        }
        if ($val > $mark) {
            $val = $mark;
        }
        $sum += $val;
    }
    return round($sum / 5.0, 2);
}

function dmportal_eval_can_doctor_access_course(PDO $pdo, int $doctorId, int $courseId): bool
{
    if ($doctorId <= 0 || $courseId <= 0) {
        return false;
    }

    $stmt2 = $pdo->prepare('SELECT 1 FROM courses WHERE course_id = :course_id AND doctor_id = :doctor_id LIMIT 1');
    $stmt2->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    if ($stmt2->fetchColumn()) {
        return true;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM course_doctors
             WHERE doctor_id = :doctor_id AND course_id = :course_id
             LIMIT 1"
        );
        $stmt->execute([':doctor_id' => $doctorId, ':course_id' => $courseId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
            throw $e;
        }
        return false;
    }
}

function dmportal_eval_load_course(PDO $pdo, int $courseId): ?array
{
    $stmt = $pdo->prepare('SELECT course_id, course_name, year_level, semester FROM courses WHERE course_id = :id LIMIT 1');
    $stmt->execute([':id' => $courseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dmportal_eval_fetch_config(PDO $pdo, int $courseId, int $doctorId, ?int $termId = null): ?array
{
    $params = [':course_id' => $courseId, ':doctor_id' => $doctorId];
    $termClause = '';
    if ($termId !== null && $termId > 0) {
        $termClause = ' AND term_id = :term_id';
        $params[':term_id'] = $termId;
    }

    $stmt = $pdo->prepare('SELECT config_id FROM evaluation_configs WHERE course_id = :course_id AND doctor_id = :doctor_id' . $termClause . ' LIMIT 1');
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row && $doctorId !== 0) {
        $params[':doctor_id'] = 0;
        $stmt->execute($params);
        $row = $stmt->fetch();
    }

    if (!$row) {
        return null;
    }

    $itemsStmt = $pdo->prepare(
        'SELECT item_id, category_key, item_label, weight, sort_order
         FROM evaluation_config_items
         WHERE config_id = :config_id
         ORDER BY sort_order ASC, item_id ASC'
    );
    $itemsStmt->execute([':config_id' => (int)$row['config_id']]);
    $items = $itemsStmt->fetchAll();

    return [
        'config_id' => (int)$row['config_id'],
        'items' => $items,
    ];
}
