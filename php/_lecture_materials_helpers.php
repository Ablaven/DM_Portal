<?php

declare(strict_types=1);

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
}

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

function dmportal_is_doctor_assigned_to_course(PDO $pdo, int $doctorId, int $courseId): bool
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

function dmportal_get_course_folder_name(PDO $pdo, int $courseId): string
{
    $stmt = $pdo->prepare('SELECT course_name FROM courses WHERE course_id = :course_id LIMIT 1');
    $stmt->execute([':course_id' => $courseId]);
    $courseName = $stmt->fetchColumn();
    
    if ($courseName === false || trim((string)$courseName) === '') {
        return (string)$courseId;
    }
    
    $sanitized = trim((string)$courseName);
    $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $sanitized);
    $sanitized = preg_replace('/\s+/', '_', $sanitized);
    $sanitized = preg_replace('/_+/', '_', $sanitized);
    $sanitized = trim($sanitized, '_');
    
    if ($sanitized === '') {
        return (string)$courseId;
    }
    
    if (mb_strlen($sanitized) > 100) {
        $sanitized = mb_substr($sanitized, 0, 100);
    }
    
    return $sanitized;
}

function dmportal_get_upload_dir(PDO $pdo, int $courseId): string
{
    $folderName = dmportal_get_course_folder_name($pdo, $courseId);
    return __DIR__ . '/../lectures/' . $folderName . '/';
}

function dmportal_ensure_upload_dir(PDO $pdo, int $courseId): bool
{
    $lecturesRoot = __DIR__ . '/../lectures/';
    $folderName   = dmportal_get_course_folder_name($pdo, $courseId);
    $courseDir    = $lecturesRoot . $folderName . '/';

    if (!is_dir($lecturesRoot)) {
        if (!@mkdir($lecturesRoot, 0755, true)) {
            return false;
        }
    }

    $rootSentinel = $lecturesRoot . 'index.php';
    if (!file_exists($rootSentinel)) {
        if (@file_put_contents($rootSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    if (!is_dir($courseDir)) {
        if (!@mkdir($courseDir, 0755, true)) {
            return false;
        }
    }

    $courseSentinel = $courseDir . 'index.php';
    if (!file_exists($courseSentinel)) {
        if (@file_put_contents($courseSentinel, '<?php // Forbidden') === false) {
            return false;
        }
    }

    return true;
}

function dmportal_get_stored_file_path(PDO $pdo, int $courseId, string $storedFilename): string
{
    return dmportal_get_upload_dir($pdo, $courseId) . $storedFilename;
}