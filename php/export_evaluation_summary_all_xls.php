<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';
require_once __DIR__ . '/_term_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

function bad_request(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

function academic_year_from_date(DateTimeInterface $date): string
{
    $y = (int)$date->format('Y');
    $m = (int)$date->format('n');
    $startYear = ($m >= 9) ? $y : ($y - 1);
    return $startYear . '/' . ($startYear + 1);
}

try {
    auth_require_login(true);
    auth_require_roles(['admin'], true);

    $yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

    if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) {
        bad_request('year_level must be 1-3 or empty.');
    }
    if ($semester !== 0 && ($semester < 1 || $semester > 2)) {
        bad_request('semester must be 1-2 or empty.');
    }

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    $courseWhere = [];
    $params = [];
    if ($yearLevel > 0) { $courseWhere[] = 'year_level = :year_level'; $params[':year_level'] = $yearLevel; }
    if ($semester > 0) { $courseWhere[] = 'semester = :semester'; $params[':semester'] = $semester; }
    $whereSql = $courseWhere ? ('WHERE ' . implode(' AND ', $courseWhere)) : '';

    $coursesStmt = $pdo->prepare(
        "SELECT course_id, course_name, year_level, semester
         FROM courses
         $whereSql
         ORDER BY year_level ASC, semester ASC, course_name ASC"
    );
    $coursesStmt->execute($params);
    $courses = $coursesStmt->fetchAll();

    if (!$courses) {
        bad_request('No courses found for the selected filters.');
    }

    $rows = [];
    $rowHeights = [];
    $styleMap = [];

    $xlsx = new SimpleXlsxWriter();

    $yearSemLabel = 'All Years/Semesters';
    if ($yearLevel > 0 && $semester > 0) {
        $yearSemLabel = 'Year ' . $yearLevel . ' / Sem ' . $semester;
    } elseif ($yearLevel > 0) {
        $yearSemLabel = 'Year ' . $yearLevel . ' / All Semesters';
    } elseif ($semester > 0) {
        $yearSemLabel = 'All Years / Sem ' . $semester;
    }
    if ($termId > 0) {
        $yearSemLabel .= ' / Term ' . $termId;
    }

    $academicYear = academic_year_from_date(new DateTimeImmutable('now'));
    $faculty = 'Management';
    $department = 'Digital Marketing';

    $courseIdMap = [];
    foreach ($courses as $course) {
        $courseIdMap[(int)$course['course_id']] = $course;
    }

    $studentsStmt = $pdo->prepare(
        'SELECT student_id, full_name, student_code, year_level
         FROM students'
        . ($yearLevel > 0 ? ' WHERE year_level = :year_level' : '')
        . ' ORDER BY full_name ASC'
    );
    $studentsStmt->execute($yearLevel > 0 ? [':year_level' => $yearLevel] : []);
    $students = $studentsStmt->fetchAll();

    if (!$students) {
        bad_request('No students found for the selected filters.');
    }

    $header = ['Student Code', 'Student'];
    foreach ($courses as $course) {
        $header[] = (string)$course['course_name'];
    }
    $totalCols = count($header);

    $padRow = static function (array $row, int $cols): array {
        return count($row) < $cols ? array_pad($row, $cols, '') : $row;
    };

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
    $styleMap[] = array_fill(0, count($header), $xlsx->styleHeaderSmall());

    $colWidths = array_fill(0, $totalCols, 14);
    $colWidths[0] = 16;
    $colWidths[1] = 40;

    foreach ($courses as $index => $course) {
        $labelLen = mb_strlen((string)($course['course_name'] ?? ''));
        $colWidths[2 + $index] = $labelLen >= 18 ? 20 : 14;
    }

    $courseIds = array_keys($courseIdMap);
    $gradeMap = [];
    if ($courseIds) {
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $gradeSql =
            "SELECT course_id, student_id, final_score, updated_at
             FROM evaluation_grades
             WHERE course_id IN ($placeholders)";
        $gradeParams = $courseIds;
        if ($termId > 0) {
            $gradeSql .= ' AND term_id = ?';
            $gradeParams[] = $termId;
        }
        $gradeSql .= ' ORDER BY updated_at DESC, grade_id DESC';

        $gradesStmt = $pdo->prepare($gradeSql);
        $gradesStmt->execute($gradeParams);
        $gradeRows = $gradesStmt->fetchAll();

        foreach ($gradeRows as $row) {
            $cid = (int)$row['course_id'];
            $sid = (int)$row['student_id'];
            if (!isset($gradeMap[$sid])) {
                $gradeMap[$sid] = [];
            }
            if (!isset($gradeMap[$sid][$cid])) {
                $gradeMap[$sid][$cid] = $row;
            }
        }
    }

    foreach ($students as $student) {
        $sid = (int)$student['student_id'];
        $row = [
            (string)($student['student_code'] ?? $sid),
            (string)$student['full_name'],
        ];

        foreach ($courses as $course) {
            $cid = (int)$course['course_id'];
            $grade = $gradeMap[$sid][$cid] ?? null;
            $row[] = $grade && $grade['final_score'] !== null ? number_format((float)$grade['final_score'], 2) : '';
        }

        $rows[] = $row;
        $rowHeights[] = 20;
        $rowStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        $rowStyle[1] = $xlsx->styleCellSmallBoldLeft();
        $styleMap[] = $rowStyle;
    }

    $xlsx->addSheet(
        'Final Grades',
        $rows,
        [
            'colWidths' => $colWidths,
            'rowHeights' => $rowHeights,
            'styleMap' => $styleMap,
            'freezeTopRows' => 6,
        ]
    );

    $fileName = 'Final Grades - All Subjects.xlsx';
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
