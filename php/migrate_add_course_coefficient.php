<?php

declare(strict_types=1);

// One-time migration helper.
// Run manually once (admin): http://localhost/Digital%20Marketing%20Portal/php/migrate_add_course_coefficient.php

require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management']);

$pdo = get_pdo();

try {
    // Add column if missing.
    $pdo->exec("ALTER TABLE courses ADD COLUMN coefficient DECIMAL(6,2) NOT NULL DEFAULT 1.00");
    echo "OK: coefficient column added.";
} catch (PDOException $e) {
    // 1060 = Duplicate column
    if ((int)($e->errorInfo[1] ?? 0) === 1060) {
        echo "OK: coefficient already exists.";
    } else {
        http_response_code(500);
        echo "Failed: " . htmlspecialchars($e->getMessage());
    }
}
