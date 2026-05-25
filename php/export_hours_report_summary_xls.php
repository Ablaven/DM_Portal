<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_hours_report_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

function bad_request(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

try {
    auth_require_login(true);
    auth_require_roles(['admin', 'teacher'], true);

    $user = auth_current_user();
    $role = (string)($user['role'] ?? '');
    $doctorScopeId = 0;
    if ($role === 'teacher') {
        $doctorScopeId = (int)($user['doctor_id'] ?? 0);
        if ($doctorScopeId <= 0) {
            bad_request('No professor data available for this account.');
        }
    }

    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        bad_request('year_level must be 1-3 or empty.');
    }
    if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
        bad_request('semester must be 1-2 or empty.');
    }

    $pdo = get_pdo();
    $doctors = dmportal_fetch_hours_report($pdo, $yearLevel, $semester, $doctorScopeId, 0);

    if (!$doctors) {
        bad_request('No professor hours found for the selected filters.');
    }

    $xlsx = new SimpleXlsxWriter();
    $yearSemLabel = dmportal_hours_report_year_sem_label($yearLevel, $semester);
    $academicYear = dmportal_hours_report_academic_year_from_date(new DateTimeImmutable('now'));
    $faculty = 'Management';
    $department = 'Digital Marketing';

    $header = ['Professor', 'Allocated (h)', 'Done (h)', 'Remaining (h)', '% Complete'];
    $totalCols = count($header);

    $padRow = static function (array $row, int $cols): array {
        return count($row) < $cols ? array_pad($row, $cols, '') : $row;
    };

    $rows = [];
    $rowHeights = [];
    $styleMap = [];

    $rows[] = $padRow(['Academic Year', $academicYear], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow(['Faculty', $faculty], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow(['Department', $department], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow(['Year/Semester', $yearSemLabel], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
    $rowHeights[] = 18;

    $rows[] = $header;
    $rowHeights[] = 24;
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleHeaderSmall());

    $colWidths = [36, 14, 14, 14, 14];

    foreach ($doctors as $doctor) {
        $alloc = (float)($doctor['totals']['allocated_hours'] ?? 0);
        $done = (float)($doctor['totals']['done_hours'] ?? 0);
        $rem = (float)($doctor['totals']['remaining_hours'] ?? 0);
        $pct = $alloc > 0 ? round(($done / $alloc) * 100, 1) : 0.0;

        $rows[] = [
            (string)($doctor['full_name'] ?? ''),
            number_format($alloc, 2, '.', ''),
            number_format($done, 2, '.', ''),
            number_format($rem, 2, '.', ''),
            number_format($pct, 1, '.', '') . '%',
        ];
        $rowHeights[] = 20;
        $rowStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        $rowStyle[0] = $xlsx->styleCellSmallBoldLeft();
        $styleMap[] = $rowStyle;
    }

    $xlsx->addSheet(
        'Professors Totals',
        $rows,
        [
            'colWidths' => $colWidths,
            'rowHeights' => $rowHeights,
            'styleMap' => $styleMap,
            'freezeTopRows' => 6,
        ]
    );

    $fileName = 'Hours Report - Professors Totals.xlsx';
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
