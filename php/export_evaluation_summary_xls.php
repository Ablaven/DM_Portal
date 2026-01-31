<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_evaluation_schema_helpers.php';
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
    $courseId = (int)($_GET['course_id'] ?? 0);
    if ($courseId <= 0) {
        bad_request('course_id is required.');
    }

    $pdo = get_pdo();
    dmportal_ensure_evaluation_tables($pdo);

    $course = dmportal_eval_load_course($pdo, $courseId);
    if (!$course) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Course not found.';
        exit;
    }

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);

    if ($role === 'teacher' && !dmportal_eval_can_doctor_access_course($pdo, $doctorId, $courseId)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden.';
        exit;
    }

    $config = dmportal_eval_fetch_config($pdo, $courseId, $doctorId);
    $items = $config['items'] ?? [];

    if (!$items) {
        bad_request('No evaluation configuration found for this course.');
    }

    $studentsStmt = $pdo->prepare(
        'SELECT student_id, full_name, student_code
         FROM students
         WHERE year_level = :year_level
         ORDER BY full_name ASC'
    );
    $studentsStmt->execute([':year_level' => (int)$course['year_level']]);
    $students = $studentsStmt->fetchAll();

    $gradesStmt = $pdo->prepare(
        'SELECT grade_id, student_id, attendance_score, final_score
         FROM evaluation_grades
         WHERE course_id = :course_id AND doctor_id = :doctor_id'
    );
    $gradesStmt->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    $gradeRows = $gradesStmt->fetchAll();
    $gradeMap = [];
    foreach ($gradeRows as $r) {
        $gradeMap[(string)$r['student_id']] = $r;
    }

    $itemScoresStmt = $pdo->prepare(
        'SELECT gi.grade_id, gi.item_id, gi.score
         FROM evaluation_grade_items gi
         JOIN evaluation_grades g ON g.grade_id = gi.grade_id
         WHERE g.course_id = :course_id AND g.doctor_id = :doctor_id'
    );
    $itemScoresStmt->execute([':course_id' => $courseId, ':doctor_id' => $doctorId]);
    $itemScoreRows = $itemScoresStmt->fetchAll();
    $scoreMap = [];
    foreach ($itemScoreRows as $r) {
        $gid = (int)$r['grade_id'];
        if (!isset($scoreMap[$gid])) {
            $scoreMap[$gid] = [];
        }
        $scoreMap[$gid][(int)$r['item_id']] = (float)$r['score'];
    }

    $rows = [];
    $rowHeights = [];
    $styleMap = [];

    $xlsx = new SimpleXlsxWriter();

    $courseName = (string)($course['course_name'] ?? '');

    $doctorName = '';
    if ($doctorId > 0) {
        $doctorStmt = $pdo->prepare('SELECT full_name FROM doctors WHERE doctor_id = :doctor_id');
        $doctorStmt->execute([':doctor_id' => $doctorId]);
        $doctorName = (string)($doctorStmt->fetchColumn() ?: '');
    }

    $itemsOut = [];
    foreach ($items as $item) {
        $itemsOut[] = [
            'item_id' => (int)$item['item_id'],
            'category' => (string)$item['category_key'],
            'label' => (string)$item['item_label'],
            'weight' => (float)$item['weight'],
        ];
    }

    $header = ['Student Code', 'Student', 'Final'];
    $totalCols = count($header);
    $yearSem = 'Year ' . (string)$course['year_level'] . ((int)$course['semester'] > 0 ? (' / Sem ' . (string)$course['semester']) : '');
    $academicYear = academic_year_from_date(new DateTimeImmutable('now'));
    $faculty = 'Management';
    $department = 'Digital Marketing';

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

    $rows[] = $padRow(['Year/Semester', $yearSem], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
    $rowHeights[] = 18;

    $prof = $doctorName !== '' ? $doctorName : '__________';
    $rows[] = $padRow(['Professor Name:', $prof], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow(['Course Name:', $courseName], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
    $rowHeights[] = 22;

    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
    $rowHeights[] = 18;

    $freezeTopRows = count($rows) + 1;

    $rows[] = $header;
    $rowHeights[] = 24;
    $styleMap[] = array_fill(0, count($header), $xlsx->styleHeaderSmall());

    $colWidths = [16, 40, 12];

    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $existing = $gradeMap[(string)$sid] ?? null;
        $gradeId = $existing ? (int)$existing['grade_id'] : 0;
        $scores = $gradeId ? ($scoreMap[$gradeId] ?? []) : [];

        $attendance = dmportal_eval_compute_attendance($pdo, $courseId, $sid);
        $attendanceScore = $attendance['score'];
        $finalScore = null;
        if ($itemsOut) {
            $finalScore = dmportal_eval_compute_final($itemsOut, $scores, $attendanceScore);
        }

        $row = [
            (string)($s['student_code'] ?? $sid),
            (string)$s['full_name'],
            $finalScore !== null ? number_format($finalScore, 2) : '',
        ];

        $rows[] = $row;
        $rowHeights[] = 20;
        $rowStyle = array_fill(0, count($row), $xlsx->styleCellSmallBold());
        $rowStyle[1] = $xlsx->styleCellSmallBoldLeft();
        $styleMap[] = $rowStyle;
    }

    $xlsx->addSheet(
        'Summary',
        $rows,
        [
            'colWidths' => $colWidths,
            'rowHeights' => $rowHeights,
            'styleMap' => $styleMap,
            'freezeTopRows' => $freezeTopRows,
        ]
    );

    $safeCourse = preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $courseName);
    $fileName = trim((string)$safeCourse) !== '' ? (string)$safeCourse : 'Grades';
    $fileName .= ' - Final Grades.xlsx';

    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
