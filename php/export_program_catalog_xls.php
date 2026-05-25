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

/**
 * Build rows for one year sheet (metadata + Sem 1 + Sem 2 sections).
 *
 * @param array<int, array<int, list<array{course_name:string,subject_code:string}>>> $byYearSem
 * @return array{rows:list<array>, rowHeights:list<int>, styleMap:list<array>, colWidths:list<int>, freezeTopRows:int}
 */
function dmportal_build_program_catalog_year_sheet(
    SimpleXlsxWriter $xlsx,
    int $yearLevel,
    string $program,
    string $academicYear,
    string $faculty,
    string $department,
    array $byYearSem
): array {
    $totalCols = 2;
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

    $rows[] = $padRow(['Program', $program], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow(['Year Level', 'Year ' . $yearLevel], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
    $rowHeights[] = 18;

    $freezeTopRows = 0;

    $appendSemesterSection = static function (int $semester) use (
        $xlsx,
        $totalCols,
        $padRow,
        &$rows,
        &$rowHeights,
        &$styleMap,
        $byYearSem,
        $yearLevel
    ): void {
        $rows[] = $padRow(['Semester ' . $semester], $totalCols);
        $semStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        $semStyle[0] = $xlsx->styleCellSmallBoldLeft();
        $styleMap[] = $semStyle;
        $rowHeights[] = 22;

        $rows[] = ['Course Name', 'Course ID'];
        $rowHeights[] = 24;
        $styleMap[] = array_fill(0, $totalCols, $xlsx->styleHeaderSmall());

        $courses = $byYearSem[$yearLevel][$semester] ?? [];
        foreach ($courses as $course) {
            $rows[] = [
                (string)($course['course_name'] ?? ''),
                (string)($course['subject_code'] ?? ''),
            ];
            $rowHeights[] = 20;
            $rowStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $rowStyle[0] = $xlsx->styleCellSmallBoldLeft();
            $styleMap[] = $rowStyle;
        }

        $rows[] = $padRow([], $totalCols);
        $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        $rowHeights[] = 14;
    };

    $appendSemesterSection(1);
    $freezeTopRows = 8;
    $appendSemesterSection(2);

    return [
        'rows' => $rows,
        'rowHeights' => $rowHeights,
        'styleMap' => $styleMap,
        'colWidths' => [48, 14],
        'freezeTopRows' => $freezeTopRows,
    ];
}

try {
    auth_require_login(true);
    auth_require_roles(['admin', 'management'], true);

    $program = trim((string)($_GET['program'] ?? 'Digital Marketing'));
    if ($program === '') {
        $program = 'Digital Marketing';
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT course_name, subject_code, year_level, semester
         FROM courses
         WHERE program = :program
           AND year_level BETWEEN 1 AND 3
           AND semester IN (1, 2)
         ORDER BY year_level ASC, semester ASC, course_name ASC'
    );
    $stmt->execute([':program' => $program]);
    $courseRows = $stmt->fetchAll();

    if (!$courseRows) {
        bad_request('No courses found for program: ' . $program);
    }

    $byYearSem = [];
    for ($y = 1; $y <= 3; $y++) {
        $byYearSem[$y] = [1 => [], 2 => []];
    }
    foreach ($courseRows as $r) {
        $y = (int)$r['year_level'];
        $s = (int)$r['semester'];
        if ($y < 1 || $y > 3 || ($s !== 1 && $s !== 2)) {
            continue;
        }
        $byYearSem[$y][$s][] = [
            'course_name' => (string)($r['course_name'] ?? ''),
            'subject_code' => (string)($r['subject_code'] ?? ''),
        ];
    }

    $xlsx = new SimpleXlsxWriter();
    $academicYear = dmportal_hours_report_academic_year_from_date(new DateTimeImmutable('now'));
    $faculty = 'Management';
    $department = 'Digital Marketing';

    for ($yearLevel = 1; $yearLevel <= 3; $yearLevel++) {
        $sheetData = dmportal_build_program_catalog_year_sheet(
            $xlsx,
            $yearLevel,
            $program,
            $academicYear,
            $faculty,
            $department,
            $byYearSem
        );

        $xlsx->addSheet(
            'Year ' . $yearLevel,
            $sheetData['rows'],
            [
                'colWidths' => $sheetData['colWidths'],
                'rowHeights' => $sheetData['rowHeights'],
                'styleMap' => $sheetData['styleMap'],
                'freezeTopRows' => $sheetData['freezeTopRows'],
            ]
        );
    }

    $safeProgram = preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $program);
    $fileName = trim($safeProgram) !== '' ? ($safeProgram . ' - Program Catalog.xlsx') : 'Program Catalog.xlsx';
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
