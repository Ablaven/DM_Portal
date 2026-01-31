<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';

/**
 * Ensures auth tables exist.
 * This portal started without authentication; this helper creates the minimal schema at runtime.
 */
function ensure_auth_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS portal_users (
            user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','management','teacher','student') NOT NULL,
            doctor_id BIGINT UNSIGNED NULL,
            student_id BIGINT UNSIGNED NULL,
            allowed_pages_json LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            UNIQUE KEY uniq_username (username),
            KEY idx_role (role),
            KEY idx_doctor_id (doctor_id),
            KEY idx_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function count_portal_users(PDO $pdo): int
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM portal_users');
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        // If table doesn't exist yet, create and return 0.
        ensure_auth_schema($pdo);
        return 0;
    }
}
