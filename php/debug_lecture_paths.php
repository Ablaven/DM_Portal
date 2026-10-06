<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

try {
    $pdo = get_pdo();
    dmportal_ensure_lecture_materials_table($pdo);

    echo "=== Lecture Materials Path Debug ===\n\n";

    // Get all materials
    $stmt = $pdo->query('SELECT material_id, course_id, stored_filename, original_filename FROM lecture_materials LIMIT 10');
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total materials in database: " . count($materials) . "\n\n";

    foreach ($materials as $material) {
        $materialId = (int)$material['material_id'];
        $courseId = (int)$material['course_id'];
        $storedFilename = (string)$material['stored_filename'];
        $originalFilename = (string)$material['original_filename'];

        echo "Material ID: {$materialId}\n";
        echo "Original: {$originalFilename}\n";
        echo "Stored: {$storedFilename}\n";

        // Get the course name
        $courseStmt = $pdo->prepare('SELECT course_name FROM courses WHERE course_id = :id');
        $courseStmt->execute([':id' => $courseId]);
        $courseName = $courseStmt->fetchColumn();
        echo "Course: {$courseName} (ID: {$courseId})\n";

        // Get the folder name
        $folderName = dmportal_get_course_folder_name($pdo, $courseId);
        echo "Folder name: {$folderName}\n";

        // Get the full path
        $filePath = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);
        echo "Expected path: {$filePath}\n";
        echo "File exists: " . (is_file($filePath) ? "YES ✅" : "NO ❌") . "\n";

        // Check if file exists elsewhere
        $uploadDir = dmportal_get_upload_dir($pdo, $courseId);
        echo "Upload directory: {$uploadDir}\n";
        echo "Directory exists: " . (is_dir($uploadDir) ? "YES" : "NO") . "\n";

        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            $files = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
            echo "Files in directory: " . implode(', ', $files) . "\n";
        }

        echo "\n" . str_repeat('-', 80) . "\n\n";
    }

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
