<?php

declare(strict_types=1);

/**
 * Assignment system helper functions
 * Handles database tables, folder management, permissions, and validation
 */

// ─────────────────────────────────────────────────────────────────────────────
// TABLE CREATION
// ─────────────────────────────────────────────────────────────────────────────

function dmportal_ensure_assignments_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS assignments (\n"
        . "  assignment_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  course_id             BIGINT UNSIGNED NOT NULL,\n"
        . "  doctor_id             BIGINT UNSIGNED NOT NULL,\n"
        . "  year_level            INT NOT NULL,\n"
        . "  semester              INT NOT NULL,\n"
        . "  title                 VARCHAR(255) NOT NULL,\n"
        . "  description           TEXT DEFAULT NULL,\n"
        . "  deadline              DATETIME NOT NULL,\n"
        . "  allow_multiple_files  TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "\n"
        . "  PRIMARY KEY (assignment_id),\n"
        . "  KEY idx_assignment_course (course_id),\n"
        . "  KEY idx_assignment_doctor (doctor_id),\n"
        . "  KEY idx_assignment_year_sem (year_level, semester),\n"
        . "  KEY idx_assignment_deadline (deadline),\n"
        . "\n"
        . "  CONSTRAINT fk_assignment_course\n"
        . "      FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,\n"
        . "  CONSTRAINT fk_assignment_doctor\n"
        . "      FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dmportal_ensure_assignment_submissions_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS assignment_submissions (\n"
        . "  submission_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  assignment_id      BIGINT UNSIGNED NOT NULL,\n"
        . "  student_id         BIGINT UNSIGNED NOT NULL,\n"
        . "  original_filename  VARCHAR(255) NOT NULL,\n"
        . "  stored_filename    VARCHAR(255) NOT NULL,\n"
        . "  file_type          ENUM('pdf','pptx','zip','rar') NOT NULL,\n"
        . "  file_size_bytes    BIGINT UNSIGNED NOT NULL,\n"
        . "  submitted_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "\n"
        . "  PRIMARY KEY (submission_id),\n"
        . "  UNIQUE KEY uq_submission_stored_filename (stored_filename),\n"
        . "  KEY idx_submission_assignment (assignment_id),\n"
        . "  KEY idx_submission_student (student_id),\n"
        . "  KEY idx_submission_date (submitted_at),\n"
        . "\n"
        . "  CONSTRAINT fk_submission_assignment\n"
        . "      FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,\n"
        . "  CONSTRAINT fk_submission_student\n"
        . "      FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// QUERY HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get assignment by ID with course and doctor details
 */
function dmportal_get_assignment_by_id(PDO $pdo, int $assignmentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, c.course_name, c.subject_code, d.full_name AS doctor_name'
        . ' FROM assignments a'
        . ' JOIN courses c ON c.course_id = a.course_id'
        . ' JOIN doctors d ON d.doctor_id = a.doctor_id'
        . ' WHERE a.assignment_id = :assignment_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':assignment_id' => $assignmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Get submission by ID
 */
function dmportal_get_submission_by_id(PDO $pdo, int $submissionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT sub.*, s.full_name AS student_name, a.assignment_id, a.course_id'
        . ' FROM assignment_submissions sub'
        . ' JOIN students s ON s.student_id = sub.student_id'
        . ' JOIN assignments a ON a.assignment_id = sub.assignment_id'
        . ' WHERE sub.submission_id = :submission_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':submission_id' => $submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Check if a doctor is assigned to a course
 */
function dmportal_is_doctor_assigned_to_course_assignment(PDO $pdo, int $doctorId, int $courseId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM course_doctors'
        . ' WHERE doctor_id = :doctor_id AND course_id = :course_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':doctor_id' => $doctorId, ':course_id' => $courseId]);
    if ($stmt->fetchColumn() !== false) {
        return true;
    }

    $stmt2 = $pdo->prepare(
        'SELECT 1 FROM courses'
        . ' WHERE course_id = :course_id AND doctor_id = :doctor_id'
        . ' LIMIT 1'
    );
    $stmt2->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    return $stmt2->fetchColumn() !== false;
}

/**
 * Check if a student is enrolled in a course
 */
function dmportal_is_student_enrolled_in_course(PDO $pdo, int $studentId, int $courseId): bool
{
    // Check course program and year/semester match student's enrollment
    $stmt = $pdo->prepare(
        'SELECT s.program, s.year_level, s.semester'
        . ' FROM students s'
        . ' WHERE s.student_id = :student_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        return false;
    }

    $stmt2 = $pdo->prepare(
        'SELECT 1 FROM courses'
        . ' WHERE course_id = :course_id'
        . ' AND program = :program'
        . ' AND year_level = :year_level'
        . ' AND semester = :semester'
        . ' LIMIT 1'
    );
    $stmt2->execute([
        ':course_id' => $courseId,
        ':program' => $student['program'],
        ':year_level' => $student['year_level'],
        ':semester' => $student['semester'],
    ]);
    
    return $stmt2->fetchColumn() !== false;
}

/**
 * Check if assignment deadline has passed
 */
function dmportal_is_assignment_deadline_passed(PDO $pdo, int $assignmentId): bool
{
    $stmt = $pdo->prepare(
        'SELECT deadline FROM assignments WHERE assignment_id = :assignment_id LIMIT 1'
    );
    $stmt->execute([':assignment_id' => $assignmentId]);
    $deadline = $stmt->fetchColumn();
    
    if ($deadline === false) {
        return true; // Assignment not found, treat as expired
    }
    
    return strtotime($deadline) < time();
}

/**
 * Get student's existing submissions for an assignment
 */
function dmportal_get_student_submissions(PDO $pdo, int $assignmentId, int $studentId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM assignment_submissions'
        . ' WHERE assignment_id = :assignment_id AND student_id = :student_id'
        . ' ORDER BY submitted_at DESC'
    );
    $stmt->execute([':assignment_id' => $assignmentId, ':student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Count student submissions for an assignment
 */
function dmportal_count_student_submissions(PDO $pdo, int $assignmentId, int $studentId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM assignment_submissions'
        . ' WHERE assignment_id = :assignment_id AND student_id = :student_id'
    );
    $stmt->execute([':assignment_id' => $assignmentId, ':student_id' => $studentId]);
    return (int)$stmt->fetchColumn();
}

// ─────────────────────────────────────────────────────────────────────────────
// FOLDER MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get sanitized assignment folder name
 */
function dmportal_get_assignment_folder_name(PDO $pdo, int $assignmentId): string
{
    $stmt = $pdo->prepare('SELECT title FROM assignments WHERE assignment_id = :assignment_id LIMIT 1');
    $stmt->execute([':assignment_id' => $assignmentId]);
    $title = $stmt->fetchColumn();
    
    if ($title === false || trim((string)$title) === '') {
        return 'assignment_' . $assignmentId;
    }
    
    $sanitized = trim((string)$title);
    $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $sanitized);
    $sanitized = preg_replace('/\s+/', '_', $sanitized);
    $sanitized = preg_replace('/_+/', '_', $sanitized);
    $sanitized = trim($sanitized, '_');
    
    if ($sanitized === '') {
        return 'assignment_' . $assignmentId;
    }
    
    if (mb_strlen($sanitized) > 100) {
        $sanitized = mb_substr($sanitized, 0, 100);
    }
    
    return $sanitized . '_' . $assignmentId;
}

/**
 * Get assignment upload directory path
 */
function dmportal_get_assignment_upload_dir(PDO $pdo, int $assignmentId): string
{
    $folderName = dmportal_get_assignment_folder_name($pdo, $assignmentId);
    $baseDir = realpath(__DIR__ . '/../Assignments/');
    if ($baseDir === false) {
        $baseDir = __DIR__ . '/../Assignments';
    }
    return rtrim($baseDir, '/\\') . '/' . $folderName . '/';
}

/**
 * Ensure assignment upload directory exists
 */
function dmportal_ensure_assignment_upload_dir(PDO $pdo, int $assignmentId): bool
{
    $assignmentsRoot = realpath(__DIR__ . '/../Assignments/');
    if ($assignmentsRoot === false) {
        $assignmentsRoot = __DIR__ . '/../Assignments';
    }
    $assignmentsRoot = rtrim($assignmentsRoot, '/\\') . '/';
    $folderName = dmportal_get_assignment_folder_name($pdo, $assignmentId);
    $assignmentDir = $assignmentsRoot . $folderName . '/';

    if (!is_dir($assignmentsRoot)) {
        if (!@mkdir($assignmentsRoot, 0755, true)) {
            return false;
        }
    }

    $rootSentinel = $assignmentsRoot . 'index.php';
    if (!file_exists($rootSentinel)) {
        if (@file_put_contents($rootSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    if (!is_dir($assignmentDir)) {
        if (!@mkdir($assignmentDir, 0755, true)) {
            return false;
        }
    }

    $assignmentSentinel = $assignmentDir . 'index.php';
    if (!file_exists($assignmentSentinel)) {
        if (@file_put_contents($assignmentSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    return true;
}

/**
 * Get stored file path for a submission
 */
function dmportal_get_assignment_stored_file_path(PDO $pdo, int $assignmentId, string $storedFilename): string
{
    return dmportal_get_assignment_upload_dir($pdo, $assignmentId) . $storedFilename;
}

/**
 * Delete physical file for a submission
 */
function dmportal_delete_assignment_submission_file(PDO $pdo, array $submission): bool
{
    $assignmentId = (int)$submission['assignment_id'];
    $storedFilename = (string)$submission['stored_filename'];
    $filePath = dmportal_get_assignment_stored_file_path($pdo, $assignmentId, $storedFilename);
    
    if (file_exists($filePath)) {
        return @unlink($filePath);
    }
    
    return true; // File doesn't exist, consider it deleted
}
