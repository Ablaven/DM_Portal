<?php

declare(strict_types=1);

/**
 * Lecture Materials schema helper.
 *
 * Provides dmportal_ensure_lecture_materials_table() which creates the
 * lecture_materials table if it does not already exist.
 *
 * IMPORTANT: call this function BEFORE starting a transaction — DDL statements
 * (CREATE TABLE) cause an implicit commit in MySQL and must not be issued inside
 * an open transaction.
 */
function dmportal_ensure_lecture_materials_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lecture_materials (\n"
        . "  material_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  course_id           BIGINT UNSIGNED NOT NULL,\n"
        . "  doctor_id           BIGINT UNSIGNED NOT NULL,\n"
        . "  original_filename   VARCHAR(255) NOT NULL,\n"
        . "  stored_filename     VARCHAR(255) NOT NULL,\n"
        . "  file_type           ENUM('pdf','pptx') NOT NULL,\n"
        . "  file_size_bytes     BIGINT UNSIGNED NOT NULL,\n"
        . "  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,\n"
        . "  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "\n"
        . "  PRIMARY KEY (material_id),\n"
        . "  UNIQUE KEY uq_stored_filename (stored_filename),\n"
        . "  KEY idx_lm_course   (course_id),\n"
        . "  KEY idx_lm_doctor   (doctor_id),\n"
        . "  KEY idx_lm_uploader (uploaded_by_user_id),\n"
        . "\n"
        . "  CONSTRAINT fk_lm_course\n"
        . "      FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,\n"
        . "  CONSTRAINT fk_lm_doctor\n"
        . "      FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,\n"
        . "  CONSTRAINT fk_lm_user\n"
        . "      FOREIGN KEY (uploaded_by_user_id)\n"
        . "          REFERENCES portal_users(user_id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // Exceptions from PDO::exec() propagate to the caller — not silently swallowed.
}

/**
 * Fetch a single material row by ID, joined to the uploading doctor's full name.
 *
 * @return array<string,mixed>|null  Full row with `uploader_name`, or null if not found.
 */
function dmportal_get_material_by_id(PDO $pdo, int $materialId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT lm.*, d.full_name AS uploader_name'
        . ' FROM lecture_materials lm'
        . ' JOIN doctors d ON d.doctor_id = lm.doctor_id'
        . ' WHERE lm.material_id = :material_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':material_id' => $materialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Check whether a doctor is assigned to a course.
 *
 * Checks both the many-to-many `course_doctors` table and the legacy
 * `courses.doctor_id` column so that both assignment models are honoured.
 */
function dmportal_is_doctor_assigned_to_course(PDO $pdo, int $doctorId, int $courseId): bool
{
    // Many-to-many relationship table
    $stmt = $pdo->prepare(
        'SELECT 1 FROM course_doctors'
        . ' WHERE doctor_id = :doctor_id AND course_id = :course_id'
        . ' LIMIT 1'
    );
    $stmt->execute([':doctor_id' => $doctorId, ':course_id' => $courseId]);
    if ($stmt->fetchColumn() !== false) {
        return true;
    }

    // Legacy single-doctor column on courses
    $stmt2 = $pdo->prepare(
        'SELECT 1 FROM courses'
        . ' WHERE course_id = :course_id AND doctor_id = :doctor_id'
        . ' LIMIT 1'
    );
    $stmt2->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    return $stmt2->fetchColumn() !== false;
}

/**
 * Return the absolute path to the upload directory for a given course.
 *
 * The trailing slash is always included.
 */
function dmportal_get_upload_dir(int $courseId): string
{
    return __DIR__ . '/../lectures/' . $courseId . '/';
}

/**
 * Ensure the upload directory for a course exists and is protected.
 *
 * Creates `lectures/` root sentinel (`index.php`) and `lectures/{course_id}/`
 * with its own sentinel if they do not already exist.
 * Existing sentinel files are never overwritten.
 *
 * @return bool  `true` on success, `false` on any filesystem failure.
 */
function dmportal_ensure_upload_dir(int $courseId): bool
{
    $lecturesRoot = __DIR__ . '/../lectures/';
    $courseDir    = $lecturesRoot . $courseId . '/';

    // Ensure root lectures/ directory
    if (!is_dir($lecturesRoot)) {
        if (!@mkdir($lecturesRoot, 0755, true)) {
            return false;
        }
    }

    // Write root sentinel if missing
    $rootSentinel = $lecturesRoot . 'index.php';
    if (!file_exists($rootSentinel)) {
        if (@file_put_contents($rootSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    // Ensure per-course directory
    if (!is_dir($courseDir)) {
        if (!@mkdir($courseDir, 0755, true)) {
            return false;
        }
    }

    // Write per-course sentinel if missing
    $courseSentinel = $courseDir . 'index.php';
    if (!file_exists($courseSentinel)) {
        if (@file_put_contents($courseSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    return true;
}

/**
 * Return the absolute path to a stored file inside its course upload directory.
 */
function dmportal_get_stored_file_path(int $courseId, string $storedFilename): string
{
    return dmportal_get_upload_dir($courseId) . $storedFilename;
}
