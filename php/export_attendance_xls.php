<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_attendance_schema_helpers.php';
require_once __DIR__ . '/_xlsx_writer.php';

auth_require_login();

// Course-wide Attendance XLSX export.
//
// Request params:
// - course_id (required)
// - start_week_id (required)
// - weeks (optional, default 20)
//
// Output layout:
// - Left fixed columns: Index | Student_ID | Name
// - Then for each "week slot" (placeholder): Date | Status
//   Date is shown horizontally in the header row.
//   Status is P/A per student (vertical down rows).
//
// Permissions:
// - Admin/Management: can export any course
// - Teacher: can export only courses that have at least one scheduled slot for their doctor_id

function bad_request(string $m): void {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo $m;
    exit;
}

/**
 * Convert a 1-based column number to an Excel column name (A, B, ..., Z, AA, ...).
 */
function excel_col_name(int $n): string {
    $name = '';
    while ($n > 0) {
        $n--; // 0-based
        $name = chr(65 + ($n % 26)) . $name;
        $n = intdiv($n, 26);
    }
    return $name;
}

/**
 * Compute academic year string (e.g. "2025/2026") from a date.
 * Uses rule: Sep (9) and later starts the new academic year.
 */
function academic_year_from_date(DateTimeImmutable $d): string {
    $y = (int)$d->format('Y');
    $m = (int)$d->format('n');
    $startYear = ($m >= 9) ? $y : ($y - 1);
    return $startYear . '/' . ($startYear + 1);
}

/**
 * Compute session date from week start date (assumed Sunday) + day-of-week.
 */
function session_date_from_week_start(string $weekStartYmd, string $dayOfWeek): ?DateTimeImmutable {
    try {
        $base = new DateTimeImmutable($weekStartYmd);
    } catch (Throwable $e) {
        return null;
    }

    $offsetMap = [
        'Sun' => 0,
        'Mon' => 1,
        'Tue' => 2,
        'Wed' => 3,
        'Thu' => 4,
    ];

    $offset = $offsetMap[$dayOfWeek] ?? null;
    if ($offset === null) return null;

    return $base->modify('+' . $offset . ' day');
}

try {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $endWeekId = (int)($_GET['end_week_id'] ?? 0);

    if ($courseId <= 0) bad_request('course_id is required');
    if ($endWeekId <= 0) bad_request('end_week_id is required');

    $pdo = get_pdo();
    dmportal_ensure_attendance_records_table($pdo);

    // Course meta
    $cStmt = $pdo->prepare('SELECT course_id, course_name, program, year_level, semester FROM courses WHERE course_id = :id LIMIT 1');
    $cStmt->execute([':id' => $courseId]);
    $course = $cStmt->fetch();
    if (!$course) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Course not found';
        exit;
    }

    $yearLevel = (int)($course['year_level'] ?? 0);
    $semester = (int)($course['semester'] ?? 0);

    // Permission checks
    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $teacherDoctorId = null;

    if ($role === 'teacher') {
        $teacherDoctorId = (int)($u['doctor_id'] ?? 0);
        if ($teacherDoctorId <= 0) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Teacher account is missing doctor_id.';
            exit;
        }

        // Teacher must have at least one schedule for this course.
        $ownStmt = $pdo->prepare('SELECT 1 FROM doctor_schedules WHERE doctor_id = :did AND course_id = :cid LIMIT 1');
        $ownStmt->execute([':did' => $teacherDoctorId, ':cid' => $courseId]);
        if (!$ownStmt->fetchColumn()) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Forbidden';
            exit;
        }
    }

    // HISTORY export: include all weeks up to the selected end week.
    // We only include weeks that actually have schedules for this course.
    $wStmt = $pdo->prepare(
        'SELECT DISTINCT w.week_id, w.label, w.start_date, w.end_date
         FROM weeks w
         JOIN doctor_schedules s ON s.week_id = w.week_id
         WHERE s.course_id = :course_id
           AND w.week_id <= :end_week_id
         ORDER BY w.week_id ASC'
    );
    $wStmt->execute([':course_id' => $courseId, ':end_week_id' => $endWeekId]);
    $weeks = $wStmt->fetchAll();

    $weekIds = array_map(fn($w) => (int)$w['week_id'], $weeks);

    // Build date-groups directly per lecture (fixes multi-day courses):
    // dateKey (Y-m-d) => ['date_obj'=>DateTimeImmutable,'date_str'=>'d/m','sessions'=>[['schedule_id'=>..,'slot_number'=>..],...]]
    $dateGroups = [];
    $doctorNameForHeader = '';

    if (count($weekIds) > 0) {
        $in = implode(',', array_fill(0, count($weekIds), '?'));
        $params = $weekIds;
        array_unshift($params, $courseId);

        $sql =
            'SELECT s.schedule_id, s.week_id, s.day_of_week, s.slot_number,
                    d.full_name AS doctor_name,
                    w.start_date AS week_start
             FROM doctor_schedules s
             JOIN doctors d ON d.doctor_id = s.doctor_id
             JOIN weeks w ON w.week_id = s.week_id
             WHERE s.course_id = ?
               AND s.week_id IN (' . $in . ')';

        // If teacher, restrict to their own schedules.
        if ($teacherDoctorId !== null) {
            $sql .= ' AND s.doctor_id = ' . (int)$teacherDoctorId;
        }

        $sql .= ' ORDER BY s.week_id ASC, s.day_of_week ASC, s.slot_number ASC, s.schedule_id ASC';

        $sStmt = $pdo->prepare($sql);
        $sStmt->execute($params);
        $all = $sStmt->fetchAll();

        foreach ($all as $r) {
            $weekStart = (string)($r['week_start'] ?? '');
            $day = (string)($r['day_of_week'] ?? '');
            $dt = ($weekStart !== '') ? session_date_from_week_start($weekStart, $day) : null;
            if (!$dt) continue;

            $dateKey = $dt->format('Y-m-d');
            if (!isset($dateGroups[$dateKey])) {
                $dateGroups[$dateKey] = [
                    'date_obj' => $dt,
                    'date_str' => $dt->format('d/m'),
                    'sessions' => [],
                ];
            }

            $dateGroups[$dateKey]['sessions'][] = [
                'schedule_id' => (int)$r['schedule_id'],
                'slot_number' => (int)($r['slot_number'] ?? 0),
            ];

            $dn = (string)($r['doctor_name'] ?? '');
            if ($dn !== '' && $doctorNameForHeader === '') {
                $doctorNameForHeader = $dn;
            }
        }
    }

    // Sort sessions within each date by slot_number
    foreach ($dateGroups as $k => $g) {
        usort($dateGroups[$k]['sessions'], fn($a, $b) => ($a['slot_number'] <=> $b['slot_number']) ?: ($a['schedule_id'] <=> $b['schedule_id']));
    }

    $dateKeys = array_keys($dateGroups);
    sort($dateKeys);

    // Gather needed schedule_ids for attendance lookup
    $scheduleIds = [];
    foreach ($dateKeys as $dk) {
        foreach (($dateGroups[$dk]['sessions'] ?? []) as $sess) {
            $scheduleIds[] = (int)($sess['schedule_id'] ?? 0);
        }
    }
    $scheduleIds = array_values(array_unique(array_filter($scheduleIds, fn($x) => $x > 0)));

    // Students (currently per year; later we will scope by program/department)
    $studentsStmt = $pdo->prepare(
        'SELECT student_id, full_name, student_code
         FROM students
         WHERE year_level = :y
         ORDER BY full_name ASC'
    );
    $studentsStmt->execute([':y' => $yearLevel]);
    $students = $studentsStmt->fetchAll();

    // scheduleIds already built from $dateGroups above

    // Load attendance records for these schedule_ids
    $attendance = []; // schedule_id => student_id => status
    if (count($scheduleIds) > 0) {
        $in = implode(',', array_fill(0, count($scheduleIds), '?'));
        $aStmt = $pdo->prepare('SELECT schedule_id, student_id, status FROM attendance_records WHERE schedule_id IN (' . $in . ')');
        $aStmt->execute($scheduleIds);
        foreach ($aStmt->fetchAll() as $r) {
            $sid = (int)$r['schedule_id'];
            $st = (int)$r['student_id'];
            $attendance[$sid][$st] = strtoupper((string)$r['status']);
        }
    }

    // Header values
    $courseName = (string)($course['course_name'] ?? '');
    $yearSem = 'Year ' . $yearLevel . ($semester > 0 ? (' / Sem ' . $semester) : '');

    // Academic year computed from first available session date or first week start date
    $academicYear = '__________';
    if (!empty($dateKeys[0]) && isset($dateGroups[$dateKeys[0]]['date_obj']) && $dateGroups[$dateKeys[0]]['date_obj'] instanceof DateTimeImmutable) {
        $academicYear = academic_year_from_date($dateGroups[$dateKeys[0]]['date_obj']);
    } elseif (!empty($weeks[0]['start_date'])) {
        try {
            $academicYear = academic_year_from_date(new DateTimeImmutable((string)$weeks[0]['start_date']));
        } catch (Throwable $e) {
        }
    }

    // Fixed values for now (future: make these dynamic per faculty/department)
    $faculty = 'Management';
    $department = 'Digital Marketing';

    // Build XLSX
    $xlsx = new SimpleXlsxWriter();

    // dateGroups/dateKeys already built above

    // Columns:
    // 0 Student_ID | 1 Name | then one column per lecture session
    $leftCols = 2;
    $lectureCols = 0;
    foreach ($dateKeys as $dk) {
        $lectureCols += count($dateGroups[$dk]['sessions']);
    }
    $totalCols = $leftCols + $lectureCols;
    $lastColName = excel_col_name($totalCols);

    $rows = [];
    $styleMap = [];
    $merges = [];

    // Helper: ensure rows have consistent width (prevents Excel from showing odd "gaps")
    $padRow = static function (array $row, int $cols): array {
        if (count($row) < $cols) {
            $row = array_pad($row, $cols, '');
        }
        return $row;
    };

    // Top header block (pad to full width so columns align with the grid)
    $rows[] = $padRow(['Academic Year', $academicYear], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    // Keep labels simple per request
    $rows[] = $padRow(['Faculty', $faculty], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    $rows[] = $padRow(['Department', $department], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    $rows[] = $padRow(['Year/Semester', $yearSem], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    // Blank row
    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());

    // Professor row
    $prof = $doctorNameForHeader !== '' ? $doctorNameForHeader : '__________';
    $rows[] = $padRow(['Professor Name:', $prof], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    // Course row
    $rows[] = $padRow(['Course Name:', $courseName], $totalCols);
    $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];

    // Blank row
    $rows[] = $padRow([], $totalCols);
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());

    // Table header: 1 row
    // Row 1: dates (merged across number of sessions that date)
    $hdrDates = array_fill(0, $totalCols, '');

    // Excel row number (1-based) where $hdrDates will be written.
    $hdrDatesExcelRow = count($rows) + 1;

    $hdrDates[0] = 'Student_ID';
    $hdrDates[1] = 'Name';

    $colPtr = $leftCols;
    foreach ($dateKeys as $dk) {
        $dateStr = (string)($dateGroups[$dk]['date_str'] ?? '');
        $sessions = (array)($dateGroups[$dk]['sessions'] ?? []);
        $count = count($sessions);
        if ($count <= 0) continue;

        // Date header occupies the first cell, and we merge across the range.
        $hdrDates[$colPtr] = $dateStr;
        if ($count > 1) {
            $start = excel_col_name($colPtr + 1) . (string)$hdrDatesExcelRow;
            $end = excel_col_name($colPtr + $count) . (string)$hdrDatesExcelRow;
            $merges[] = $start . ':' . $end;
        }

        $colPtr += $count;
    }

    $rows[] = $hdrDates;
    $styleMap[] = array_fill(0, $totalCols, $xlsx->styleHeaderSmall());

    // Data rows
    $presentStyle = $xlsx->styleFillBold(XlsxColor::pastelize('22C55E', 0.80));
    $absentStyle  = $xlsx->styleCellSmallBold();

    foreach ($students as $s) {  
        $studentId = (int)$s['student_id'];
        $studentCode = (string)($s['student_code'] ?? '');
        $name = (string)($s['full_name'] ?? '');

        $r = array_fill(0, $totalCols, '');
        $r[0] = $studentCode !== '' ? $studentCode : (string)$studentId;
        $r[1] = $name;

        $rowStyles = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        // Left-align only the student Name cell (keep everything else centered)
        $rowStyles[1] = $xlsx->styleCellSmallBoldLeft();

        $colPtr = $leftCols;
        foreach ($dateKeys as $dk) {
            $sessions = (array)($dateGroups[$dk]['sessions'] ?? []);
            foreach ($sessions as $sess) {
                $schedId = (int)($sess['schedule_id'] ?? 0);
                if ($schedId <= 0) {
                    $colPtr++;
                    continue;
                }

                $st = strtoupper((string)($attendance[$schedId][$studentId] ?? 'ABSENT'));
                $isPresent = ($st === 'PRESENT');
                $r[$colPtr] = $isPresent ? 'P' : 'X ';
                $rowStyles[$colPtr] = $isPresent ? $presentStyle : $absentStyle;
                $colPtr++;
            }
        }

        $rows[] = $r;
        $styleMap[] = $rowStyles;
    }

    // --- Remove unintended blank "gap" column (if present) ---
    // Some exports may end up with an empty column just before the first Date/attendance column.
    // Detect pattern: an empty header cell followed by a non-empty one, starting from the first lecture column.
    $gapColIdx = null; // 0-based
    $hdrRowIdx = count($rows) - count($students) - 1; // index of $hdrDates row in $rows
    if ($hdrRowIdx >= 0 && isset($rows[$hdrRowIdx]) && is_array($rows[$hdrRowIdx])) {
        $hdr = $rows[$hdrRowIdx];
        for ($i = $leftCols; $i < $totalCols - 1; $i++) {
            $a = (string)($hdr[$i] ?? '');
            $b = (string)($hdr[$i + 1] ?? '');
            if ($a === '' && $b !== '') {
                $gapColIdx = $i;
                break;
            }
        }
    }

    // Helper: Excel column letters <-> number
    $excelColToNum = static function (string $letters): int {
        $letters = strtoupper($letters);
        $n = 0;
        for ($k = 0; $k < strlen($letters); $k++) {
            $ch = ord($letters[$k]);
            if ($ch < 65 || $ch > 90) continue;
            $n = $n * 26 + ($ch - 64);
        }
        return $n;
    };

    $numToExcelCol = static function (int $n): string {
        return excel_col_name($n);
    };

    if ($gapColIdx !== null) {
        foreach ($rows as $ri => $row) {
            if (!is_array($row)) continue;
            array_splice($rows[$ri], $gapColIdx, 1);
            if (isset($styleMap[$ri]) && is_array($styleMap[$ri])) {
                array_splice($styleMap[$ri], $gapColIdx, 1);
            }
        }

        // Also shift any existing column width definitions, if present.
        // (At this point we haven't built $colWidths yet, but keep logic here in case the code changes later.)
        if (isset($colWidths) && is_array($colWidths)) {
            $newColWidths = [];
            foreach ($colWidths as $ci => $w) {
                $ci = (int)$ci;
                if ($ci < $gapColIdx) {
                    $newColWidths[$ci] = $w;
                } elseif ($ci > $gapColIdx) {
                    $newColWidths[$ci - 1] = $w;
                }
                // If $ci === $gapColIdx, drop it.
            }
            $colWidths = $newColWidths;
        }

        // Adjust merges (shift columns left by 1 if they are at/after removed column)
        $removedCol1 = $gapColIdx + 1;
        $newMerges = [];
        foreach ($merges as $m) {
            if (!is_string($m) || !str_contains($m, ':')) continue;
            [$a, $b] = explode(':', $m, 2);
            if (!preg_match('/^([A-Z]+)(\d+)$/i', $a, $ma)) continue;
            if (!preg_match('/^([A-Z]+)(\d+)$/i', $b, $mb)) continue;

            $aCol = $excelColToNum($ma[1]);
            $aRow = (int)$ma[2];
            $bCol = $excelColToNum($mb[1]);
            $bRow = (int)$mb[2];

            if ($aCol >= $removedCol1) $aCol--;
            if ($bCol >= $removedCol1) $bCol--;

            // If the merge collapsed, skip it.
            if ($bCol < $aCol) continue;

            $newMerges[] = $numToExcelCol($aCol) . $aRow . ':' . $numToExcelCol($bCol) . $bRow;
        }
        $merges = $newMerges;

        // Update totalCols after removal
        $totalCols--;
    }

    // Column widths / row heights
    // Note: XLSX uses Excel width/height units, not true pixels.
    $colWidths = [];
    // Make Student_ID/Name wider, status columns narrow
    $colWidths[0] = 16; // Student_ID
    // Excel column widths are not pixels. Approx conversion: px ~= width*7 + 5 (Calibri 11).
    // 328px -> (328-5)/7 ~= 46.1
    $colWidths[1] = 46.1; // Name (~328px)
    for ($c = 2; $c < $totalCols; $c++) $colWidths[$c] = 6; // P/A cells

    $rowHeights = [];
    // Reduce overall row height ~3x (47 -> ~16) for a more compact sheet.
    for ($rIdx = 0; $rIdx < count($rows); $rIdx++) $rowHeights[$rIdx] = 16;

    $xlsx->addSheet(
        'Attendance',
        $rows,
        [
            'colWidths' => $colWidths,
            'rowHeights' => $rowHeights,
            'merges' => $merges,
            'styleMap' => $styleMap,
            // Freeze: all the meta rows + the 1 header row
            'freezeTopRows' => $hdrDatesExcelRow,
        ]
    );

    $safeCourse = preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $courseName);
    $fileName = trim((string)$safeCourse) !== '' ? (string)$safeCourse : 'Attendance';
    $fileName .= ' - up to week ' . $endWeekId . '.xlsx';

    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
