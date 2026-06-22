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

/** @return array<string,mixed> */
function read_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

    // Allow JSON POST body.
    if ($raw !== '' && str_contains($contentType, 'application/json')) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    // Allow classic form post for download-triggered submissions.
    $p = (string)($_POST['payload'] ?? '');
    if ($p !== '') {
        $decoded = json_decode($p, true);
        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

function safe_sheet_name(string $name, int $fallbackId): string
{
    $name = trim($name);
    if ($name === '') {
        $name = 'Doctor ' . $fallbackId;
    }
    $name = preg_replace('/[:\\\\\/\?\*\[\]]+/', ' ', $name) ?? $name;
    $name = trim($name);
    if ($name === '') $name = 'Doctor ' . $fallbackId;
    return mb_substr($name, 0, 31);
}

/** @return list<array{year_level:int,semester:int}> */
function normalize_filters(mixed $filters): array
{
    if (!is_array($filters)) return [];
    $out = [];
    $seen = [];

    foreach ($filters as $f) {
        if (!is_array($f)) continue;
        $y = (int)($f['year_level'] ?? 0);
        $s = (int)($f['semester'] ?? 0);
        if ($y < 1 || $y > 3) continue;
        if ($s < 1 || $s > 2) continue;
        $k = $y . '-' . $s;
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = ['year_level' => $y, 'semester' => $s];
    }

    usort($out, static fn($a, $b) => ($a['year_level'] <=> $b['year_level']) ?: ($a['semester'] <=> $b['semester']));
    return $out;
}

/** @return list<int> */
function normalize_doctor_ids(mixed $doctorIds): array
{
    if (!is_array($doctorIds)) return [];
    $out = [];
    foreach ($doctorIds as $id) {
        $n = (int)$id;
        if ($n > 0) $out[] = $n;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

try {
    auth_require_login(true);
    auth_require_roles(['admin', 'teacher'], true);

    $u = auth_current_user();
    $role = (string)($u['role'] ?? '');
    $doctorScopeId = 0;

    $payload = read_payload();

    $filters = normalize_filters($payload['filters'] ?? null);
    if (!$filters) {
        bad_request('At least one year/semester filter is required.');
    }

    $doctorIds = normalize_doctor_ids($payload['doctor_ids'] ?? null);

    if ($role === 'teacher') {
        $doctorScopeId = (int)($u['doctor_id'] ?? 0);
        if ($doctorScopeId <= 0) {
            bad_request('Teacher account is missing doctor_id.');
        }
        $doctorIds = [$doctorScopeId];
    } elseif (!$doctorIds) {
        bad_request('At least one doctor is required.');
    }

    $pdo = get_pdo();

    // Resolve names for sheet naming + ordering.
    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
    $namesStmt = $pdo->prepare("SELECT doctor_id, full_name FROM doctors WHERE doctor_id IN ($placeholders)");
    $namesStmt->execute($doctorIds);
    $nameRows = $namesStmt->fetchAll(PDO::FETCH_ASSOC);
    $nameMap = [];
    foreach ($nameRows as $r) {
        $nameMap[(int)($r['doctor_id'] ?? 0)] = (string)($r['full_name'] ?? '');
    }

    // Sort doctors by display name for consistent exports.
    usort($doctorIds, static function (int $a, int $b) use ($nameMap): int {
        $na = trim((string)($nameMap[$a] ?? ''));
        $nb = trim((string)($nameMap[$b] ?? ''));
        if ($na === '' && $nb === '') return $a <=> $b;
        if ($na === '') return 1;
        if ($nb === '') return -1;
        $cmp = strcasecmp($na, $nb);
        return $cmp !== 0 ? $cmp : ($a <=> $b);
    });

    $xlsx = new SimpleXlsxWriter();
    $academicYear = dmportal_hours_report_academic_year_from_date(new DateTimeImmutable('now'));
    $faculty = 'Management';
    $department = 'Digital Marketing';

    $tableHeader = [
        'Course',
        'Subject Code',
        'Type',
        'Program',
        'Year',
        'Sem',
        'Allocated (h)',
        'Done (h)',
        'Remaining (h)',
    ];
    $totalCols = count($tableHeader);
    $colWidths = [32, 14, 10, 14, 8, 8, 14, 14, 14];

    $padRow = static function (array $row, int $cols): array {
        return count($row) < $cols ? array_pad($row, $cols, '') : $row;
    };

    foreach ($doctorIds as $doctorId) {
        $profName = trim((string)($nameMap[$doctorId] ?? ''));
        if ($profName === '') $profName = 'Professor #' . $doctorId;

        $rows = [];
        $rowHeights = [];
        $styleMap = [];

        $rows[] = $padRow(['Done hours rule', 'Only lectures with attendance saved during the Cairo lecture window'], $totalCols);
        $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
        $rowHeights[] = 22;

        $rows[] = $padRow([], $totalCols);
        $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
        $rowHeights[] = 18;

        $grandAlloc = 0.0;
        $grandDone = 0.0;
        $grandRem = 0.0;
        $periodLabels = [];

        foreach ($filters as $idx => $f) {
            $yearLevel = (int)$f['year_level'];
            $semester = (int)$f['semester'];
            $yearSemLabel = dmportal_hours_report_year_sem_label($yearLevel, $semester);
            $periodLabels[] = $yearSemLabel;

            // Section header block
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

            $rows[] = $padRow(['Professor Name', $profName], $totalCols);
            $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
            $rowHeights[] = 22;

            $rows[] = $padRow([], $totalCols);
            $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $rowHeights[] = 18;

            // Table header
            $rows[] = $tableHeader;
            $styleMap[] = array_fill(0, $totalCols, $xlsx->styleHeaderSmall());
            $rowHeights[] = 24;

            $report = dmportal_fetch_hours_report($pdo, $yearLevel, $semester, $doctorScopeId, $doctorId);
            $doctor = $report[0] ?? ['courses' => [], 'totals' => ['allocated_hours' => 0, 'done_hours' => 0, 'remaining_hours' => 0]];
            $courses = $doctor['courses'] ?? [];

            foreach ($courses as $course) {
                $alloc = (float)($course['allocated_hours'] ?? 0);
                $done = (float)($course['done_hours'] ?? 0);
                $rem = (float)($course['remaining_hours'] ?? 0);

                $rows[] = [
                    (string)($course['course_name'] ?? ''),
                    (string)($course['subject_code'] ?? ''),
                    (string)($course['course_type'] ?? ''),
                    (string)($course['program'] ?? ''),
                    isset($course['year_level']) ? (string)(int)$course['year_level'] : '',
                    isset($course['semester']) ? (string)(int)$course['semester'] : '',
                    number_format($alloc, 2, '.', ''),
                    number_format($done, 2, '.', ''),
                    number_format($rem, 2, '.', ''),
                ];
                $rowStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
                $rowStyle[0] = $xlsx->styleCellSmallBoldLeft();
                $styleMap[] = $rowStyle;
                $rowHeights[] = 20;
            }

            $totals = $doctor['totals'] ?? [];
            $allocT = (float)($totals['allocated_hours'] ?? 0);
            $doneT = (float)($totals['done_hours'] ?? 0);
            $remT = (float)($totals['remaining_hours'] ?? 0);

            $grandAlloc += $allocT;
            $grandDone += $doneT;
            $grandRem += $remT;

            $rows[] = [
                'Totals',
                '',
                '',
                '',
                '',
                '',
                number_format($allocT, 2, '.', ''),
                number_format($doneT, 2, '.', ''),
                number_format($remT, 2, '.', ''),
            ];
            $totalStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $totalStyle[0] = $xlsx->styleCellSmallBoldLeft();
            $styleMap[] = $totalStyle;
            $rowHeights[] = 22;

            // Spacer between sections (except after last)
            if ($idx < count($filters) - 1) {
                $rows[] = $padRow([], $totalCols);
                $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
                $rowHeights[] = 14;
                $rows[] = $padRow([], $totalCols);
                $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
                $rowHeights[] = 14;
            }
        }

        if (count($filters) > 0) {
            $rows[] = $padRow([], $totalCols);
            $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $rowHeights[] = 18;

            $rows[] = $padRow([], $totalCols);
            $styleMap[] = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $rowHeights[] = 18;

            if (count($periodLabels) > 1) {
                $rows[] = $padRow(['Selected periods', implode(', ', $periodLabels)], $totalCols);
                $styleMap[] = [0 => $xlsx->styleHeaderSmall(), 1 => $xlsx->styleCellSmallBold()];
                $rowHeights[] = 22;
            }

            $rows[] = [
                'Overall Total',
                '',
                '',
                '',
                '',
                '',
                number_format(round($grandAlloc, 2), 2, '.', ''),
                number_format(round($grandDone, 2), 2, '.', ''),
                number_format(round($grandRem, 2), 2, '.', ''),
            ];
            $overallStyle = array_fill(0, $totalCols, $xlsx->styleCellSmallBold());
            $overallStyle[0] = $xlsx->styleCellSmallBoldLeft();
            $overallStyle[7] = $xlsx->styleCellSmallBold();
            $styleMap[] = $overallStyle;
            $rowHeights[] = 24;
        }

        $sheetName = safe_sheet_name($profName, $doctorId);
        $xlsx->addSheet(
            $sheetName,
            $rows,
            [
                'colWidths' => $colWidths,
                'rowHeights' => $rowHeights,
                'styleMap' => $styleMap,
                'freezeTopRows' => 7,
            ]
        );
    }

    $xlsx->download('Hours Report - Custom Export.xlsx');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}

