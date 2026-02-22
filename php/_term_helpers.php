<?php

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

function dmportal_ensure_terms_table(PDO $pdo): void
{
    dmportal_ensure_schema_version($pdo);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS academic_years (\n"
        ."  academic_year_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  label VARCHAR(50) NOT NULL,\n"
        ."  status ENUM('active','closed') NOT NULL DEFAULT 'closed',\n"
        ."  start_date DATE DEFAULT NULL,\n"
        ."  end_date DATE DEFAULT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (academic_year_id),\n"
        ."  UNIQUE KEY uq_academic_year_label (label),\n"
        ."  KEY idx_academic_year_status (status)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS terms (\n"
        ."  term_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        ."  academic_year_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,\n"
        ."  label VARCHAR(120) NOT NULL,\n"
        ."  semester TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,\n"
        ."  status ENUM('active','closed') NOT NULL DEFAULT 'closed',\n"
        ."  start_date DATE DEFAULT NULL,\n"
        ."  end_date DATE DEFAULT NULL,\n"
        ."  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        ."  PRIMARY KEY (term_id),\n"
        ."  KEY idx_terms_academic_year (academic_year_id),\n"
        ."  KEY idx_terms_semester (semester),\n"
        ."  KEY idx_terms_status (status)\n"
        .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = (int)$pdo->query('SELECT COUNT(*) FROM academic_years')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO academic_years (label, status) VALUES (:label, :status)');
        $stmt->execute([':label' => '2025-2026', ':status' => 'active']);
    }

    $count = (int)$pdo->query('SELECT COUNT(*) FROM terms')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO terms (academic_year_id, label, semester, status) VALUES (:academic_year_id, :label, :semester, :status)');
        $stmt->execute([':academic_year_id' => 1, ':label' => 'Semester 1', ':semester' => 1, ':status' => 'closed']);
        $stmt->execute([':academic_year_id' => 1, ':label' => 'Semester 2', ':semester' => 2, ':status' => 'active']);
    }
}

function dmportal_get_active_term_id(PDO $pdo, int $semester = 0, int $academicYearId = 0): int
{
    dmportal_ensure_terms_table($pdo);

    if ($academicYearId <= 0) {
        $academicYearId = dmportal_get_active_academic_year_id($pdo);
    }

    $sql = "SELECT term_id FROM terms WHERE status = 'active' AND academic_year_id = :academic_year_id";
    $params = [':academic_year_id' => $academicYearId];
    if ($semester > 0) {
        $sql .= ' AND semester = :semester';
        $params[':semester'] = $semester;
    }
    $sql .= ' ORDER BY term_id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $termId = (int)($stmt->fetchColumn() ?: 0);

    if ($termId > 0) {
        return $termId;
    }

    $fallback = (int)$pdo->query('SELECT term_id FROM terms ORDER BY term_id ASC LIMIT 1')->fetchColumn();
    return $fallback > 0 ? $fallback : 1;
}

function dmportal_get_term_id_for_week(PDO $pdo, int $weekId): int
{
    $stmt = $pdo->prepare('SELECT term_id FROM weeks WHERE week_id = :week_id LIMIT 1');
    $stmt->execute([':week_id' => $weekId]);
    $termId = (int)($stmt->fetchColumn() ?: 0);
    return $termId > 0 ? $termId : 1;
}

function dmportal_set_active_term(PDO $pdo, int $termId): void
{
    dmportal_ensure_terms_table($pdo);
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $row = $pdo->prepare('SELECT semester, academic_year_id FROM terms WHERE term_id = :term_id LIMIT 1');
        $row->execute([':term_id' => $termId]);
        $rowData = $row->fetch();
        $semester = (int)($rowData['semester'] ?? 0);
        $academicYearId = (int)($rowData['academic_year_id'] ?? 0);
        if ($semester > 0) {
            $stmt = $pdo->prepare("UPDATE terms SET status='closed' WHERE semester = :semester AND academic_year_id = :academic_year_id");
            $stmt->execute([':semester' => $semester, ':academic_year_id' => $academicYearId]);
        } else {
            $stmt = $pdo->prepare("UPDATE terms SET status='closed' WHERE academic_year_id = :academic_year_id");
            $stmt->execute([':academic_year_id' => $academicYearId]);
        }
        $stmt = $pdo->prepare("UPDATE terms SET status='active' WHERE term_id = :term_id");
        $stmt->execute([':term_id' => $termId]);
        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dmportal_get_term_id_from_request(PDO $pdo, array $source): int
{
    $termId = isset($source['term_id']) ? (int)$source['term_id'] : 0;
    if ($termId > 0) {
        return $termId;
    }
    $semester = isset($source['semester']) ? (int)$source['semester'] : 0;
    $yearId = isset($source['academic_year_id']) ? (int)$source['academic_year_id'] : 0;
    return dmportal_get_active_term_id($pdo, $semester, $yearId);
}

function dmportal_get_terms(PDO $pdo): array
{
    dmportal_ensure_terms_table($pdo);
    $stmt = $pdo->query(
        'SELECT t.term_id, t.academic_year_id, t.label, t.semester, t.status, t.start_date, t.end_date, t.created_at, ay.label AS academic_year_label'
        . ' FROM terms t'
        . ' JOIN academic_years ay ON ay.academic_year_id = t.academic_year_id'
        . ' ORDER BY t.term_id DESC'
    );
    return $stmt->fetchAll() ?: [];
}

function dmportal_create_term(PDO $pdo, string $label, int $semester, ?string $startDate, ?string $endDate): int
{
    dmportal_ensure_terms_table($pdo);

    if (!in_array($semester, [1, 2], true)) {
        throw new InvalidArgumentException('Semester must be 1 or 2.');
    }

    $stmt = $pdo->prepare('INSERT INTO terms (academic_year_id, label, semester, status, start_date, end_date) VALUES (:academic_year_id, :label, :semester, :status, :start_date, :end_date)');
    $stmt->execute([
        ':academic_year_id' => dmportal_get_active_academic_year_id($pdo),
        ':label' => $label,
        ':semester' => $semester,
        ':status' => 'closed',
        ':start_date' => $startDate ?: null,
        ':end_date' => $endDate ?: null,
    ]);
    return (int)$pdo->lastInsertId();
}

function dmportal_reset_weeks_for_term(PDO $pdo, int $termId, ?string $startDate, bool $useTransaction = true): int
{
    dmportal_ensure_weeks_prep_column($pdo);

    if ($useTransaction) {
        $pdo->beginTransaction();
    }

    $pdo->prepare("UPDATE weeks SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE term_id = :term_id AND status='active'")
        ->execute([':term_id' => $termId]);

    // Always start at Week 1 on reset — existing weeks are closed and a fresh week 1 begins.
    $label = 'Week 1';

    $stmt = $pdo->prepare('INSERT INTO weeks (term_id, label, start_date, status, is_prep) VALUES (:term_id, :label, :start_date, :status, :is_prep)');
    $stmt->execute([
        ':term_id' => $termId,
        ':label' => $label,
        ':start_date' => $startDate ?: date('Y-m-d'),
        ':status' => 'active',
        ':is_prep' => 0,
    ]);

    if ($useTransaction) {
        $pdo->commit();
    }

    return (int)$pdo->lastInsertId();
}

function dmportal_get_active_academic_year_id(PDO $pdo): int
{
    dmportal_ensure_terms_table($pdo);
    $stmt = $pdo->query("SELECT academic_year_id FROM academic_years WHERE status = 'active' ORDER BY academic_year_id DESC LIMIT 1");
    $yearId = (int)($stmt->fetchColumn() ?: 0);
    return $yearId > 0 ? $yearId : 1;
}

function dmportal_get_or_create_term(PDO $pdo, int $academicYearId, int $semester, string $label, bool $activateIfMissing = true): int
{
    $stmt = $pdo->prepare('SELECT term_id FROM terms WHERE academic_year_id = :year_id AND semester = :semester LIMIT 1');
    $stmt->execute([':year_id' => $academicYearId, ':semester' => $semester]);
    $termId = (int)($stmt->fetchColumn() ?: 0);
    if ($termId > 0) {
        return $termId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO terms (academic_year_id, label, semester, status) VALUES (:year_id, :label, :semester, :status)'
    );
    $stmt->execute([
        ':year_id' => $academicYearId,
        ':label' => $label,
        ':semester' => $semester,
        ':status' => $activateIfMissing ? 'active' : 'closed',
    ]);
    return (int)$pdo->lastInsertId();
}

function dmportal_close_academic_year(PDO $pdo, int $academicYearId): void
{
    $stmt = $pdo->prepare("UPDATE academic_years SET status='closed', end_date = COALESCE(end_date, CURDATE()) WHERE academic_year_id = :id");
    $stmt->execute([':id' => $academicYearId]);
}

function dmportal_create_next_academic_year(PDO $pdo, int $currentYearId): int
{
    $stmt = $pdo->prepare('SELECT label FROM academic_years WHERE academic_year_id = :id');
    $stmt->execute([':id' => $currentYearId]);
    $label = (string)($stmt->fetchColumn() ?: '');
    $nextLabel = dmportal_increment_academic_year_label($label);

    $stmt = $pdo->prepare('INSERT INTO academic_years (label, status, start_date) VALUES (:label, :status, :start_date)');
    $stmt->execute([
        ':label' => $nextLabel,
        ':status' => 'active',
        ':start_date' => gmdate('Y-m-d'),
    ]);
    return (int)$pdo->lastInsertId();
}

function dmportal_increment_academic_year_label(string $label): string
{
    if (preg_match('/(\d{4})\s*[-\/]\s*(\d{4})/', $label, $m)) {
        $start = (int)$m[1] + 1;
        $end = (int)$m[2] + 1;
        return sprintf('%d-%d', $start, $end);
    }

    if (preg_match('/(\d{4})/', $label, $m)) {
        $start = (int)$m[1];
        return sprintf('%d-%d', $start + 1, $start + 2);
    }

    return 'Academic Year ' . gmdate('Y') . '-' . (gmdate('Y') + 1);
}

function dmportal_auto_advance_students(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT MAX(year_level) FROM students');
    $maxYear = (int)($stmt->fetchColumn() ?: 3);

    $graduate = $pdo->prepare('UPDATE students SET year_level = 0 WHERE year_level >= :max_year');
    $graduate->execute([':max_year' => $maxYear]);

    $advance = $pdo->prepare('UPDATE students SET year_level = year_level + 1 WHERE year_level > 0 AND year_level < :max_year');
    $advance->execute([':max_year' => $maxYear]);
}

function dmportal_apply_student_actions(PDO $pdo, array $actions): void
{
    $advance = $actions['advance'] ?? [];
    $repeat = $actions['repeat'] ?? [];
    $graduate = $actions['graduate'] ?? [];

    foreach ($advance as $row) {
        $studentId = (int)($row['student_id'] ?? 0);
        $newLevel = (int)($row['year_level'] ?? 0);
        if ($studentId > 0 && $newLevel > 0) {
            $stmt = $pdo->prepare('UPDATE students SET year_level = :year_level WHERE student_id = :student_id');
            $stmt->execute([':year_level' => $newLevel, ':student_id' => $studentId]);
        }
    }

    // "repeat" students stay at their current year_level — no DB change needed

    foreach ($graduate as $studentId) {
        $studentId = (int)$studentId;
        if ($studentId > 0) {
            $stmt = $pdo->prepare('UPDATE students SET year_level = 0 WHERE student_id = :student_id');
            $stmt->execute([':student_id' => $studentId]);
        }
    }
}
