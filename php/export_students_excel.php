<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_xlsx_writer.php';

// Admin/Management only
auth_require_roles(['admin', 'management'], true);

try {
    $pdo = get_pdo();
    
    // Get filter parameters
    $yearFilter = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
    $searchFilter = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    
    // Build query
    $sql = 'SELECT full_name, student_code, year_level FROM students WHERE 1=1';
    $params = [];
    
    if ($yearFilter !== null) {
        $sql .= ' AND year_level = :year';
        $params[':year'] = $yearFilter;
    }
    
    if ($searchFilter !== '') {
        $sql .= ' AND (full_name LIKE :search OR student_code LIKE :search OR email LIKE :search)';
        $params[':search'] = '%' . $searchFilter . '%';
    }
    
    $sql .= ' ORDER BY year_level ASC, full_name ASC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Build data rows for export
    $exportRows = [];
    
    // Header row
    $exportRows[] = ['Full Name', 'Student Code', 'Year'];
    
    // Data rows
    foreach ($students as $student) {
        $exportRows[] = [
            $student['full_name'] ?? '',
            $student['student_code'] ?? '',
            'Year ' . ($student['year_level'] ?? ''),
        ];
    }
    
    // Style map: row 0 (header) gets header style
    $styleMap = [];
    $styleMap[0] = []; // header row
    for ($col = 0; $col < 3; $col++) {
        $styleMap[0][$col] = 1; // header style
    }
    
    // Create XLSX writer
    $xlsx = new SimpleXlsxWriter();
    
    // Add sheet
    $xlsx->addSheet('Students', $exportRows, [
        'styleMap' => $styleMap,
        'colWidths' => [
            0 => 30,  // Full Name
            1 => 20,  // Student Code
            2 => 15,  // Year
        ],
        'rowHeights' => [
            0 => 25, // header row height
        ],
    ]);
    
    // Output file
    $filename = 'students_export_' . date('Y-m-d_His') . '.xlsx';
    
    $xlsx->download($filename);
    exit;
    
} catch (Throwable $e) {
    // Log error for debugging
    error_log('Export students error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to generate Excel file: ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n\nStack trace:\n" . $e->getTraceAsString());
}
