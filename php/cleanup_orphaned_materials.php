<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_lecture_materials_helpers.php';

// This script finds lecture_materials records where the physical file doesn't exist
// and deletes those orphaned records from the database.

try {
    $pdo = get_pdo();
    dmportal_ensure_lecture_materials_table($pdo);

    // Get all materials
    $stmt = $pdo->query('SELECT material_id, course_id, stored_filename, original_filename FROM lecture_materials');
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orphaned = [];
    $valid = [];

    foreach ($materials as $material) {
        $materialId = (int)$material['material_id'];
        $courseId = (int)$material['course_id'];
        $storedFilename = (string)$material['stored_filename'];
        $originalFilename = (string)$material['original_filename'];

        $filePath = dmportal_get_stored_file_path($pdo, $courseId, $storedFilename);

        if (!is_file($filePath)) {
            $orphaned[] = [
                'material_id' => $materialId,
                'original_filename' => $originalFilename,
                'path' => $filePath
            ];
        } else {
            $valid[] = [
                'material_id' => $materialId,
                'original_filename' => $originalFilename
            ];
        }
    }

    echo "=== Lecture Materials Cleanup Report ===\n\n";
    echo "Total materials in database: " . count($materials) . "\n";
    echo "Valid (file exists): " . count($valid) . "\n";
    echo "Orphaned (file missing): " . count($orphaned) . "\n\n";

    if (empty($orphaned)) {
        echo "✅ No orphaned records found. Database is clean!\n";
        exit(0);
    }

    echo "=== Orphaned Records (will be deleted) ===\n";
    foreach ($orphaned as $item) {
        echo "  - ID {$item['material_id']}: {$item['original_filename']}\n";
        echo "    Missing file: {$item['path']}\n";
    }

    echo "\n";

    // Ask for confirmation if running from CLI
    if (PHP_SAPI === 'cli') {
        echo "Do you want to delete these orphaned records? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "Cancelled. No records deleted.\n";
            exit(0);
        }
    }

    // Delete orphaned records
    $deletedCount = 0;
    $deleteStmt = $pdo->prepare('DELETE FROM lecture_materials WHERE material_id = :material_id');

    foreach ($orphaned as $item) {
        $deleteStmt->execute([':material_id' => $item['material_id']]);
        $deletedCount++;
    }

    echo "\n✅ Deleted {$deletedCount} orphaned record(s) from the database.\n";
    echo "Database is now clean!\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
