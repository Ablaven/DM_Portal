<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_roles(['admin','management','student']);
require_once __DIR__ . '/_xlsx_writer.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

// XLSX export for Student Schedule (Program + Year + Semester + Week)

$program = trim((string)($_GET['program'] ?? ''));
$yearLevel = (int)($_GET['year_level'] ?? 0);
$semester = (int)($_GET['semester'] ?? 0);
$weekId = (int)($_GET['week_id'] ?? 0);

if ($program === '' || $yearLevel < 1 || $yearLevel > 3 || $semester < 1 || $semester > 2) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'program, year_level(1-3), and semester(1-2) are required';
    exit;
}

try {
    $pdo = get_pdo();

    // Ensure optional per-year doctor color table exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);
    // $semester is already in scope from $_GET; no need to re-fetch from terms table.

    if ($weekId <= 0) {
        $stmt = $pdo->prepare("SELECT week_id, label, is_ramadan FROM weeks WHERE (status='active' OR is_ramadan=1) AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
        $stmt->execute([':term_id' => $termId]);
        $wk = $stmt->fetch();
        if (!$wk) {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'No active week for this term';
            exit;
        }
        $weekId = (int)$wk['week_id'];
        $weekLabel = (string)$wk['label'];
        $isRamadanWeek = (int)($wk['is_ramadan'] ?? 0) === 1;
    } else {
        $wkStmt = $pdo->prepare('SELECT week_id, label, is_ramadan FROM weeks WHERE week_id = :id');
        $wkStmt->execute([':id' => $weekId]);
        $wk = $wkStmt->fetch();
        $weekLabel = $wk ? (string)$wk['label'] : ('Week ' . $weekId);
        $isRamadanWeek = ($wk && (int)($wk['is_ramadan'] ?? 0) === 1);
    }

    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
    $slots = [1, 2, 3, 4, 5];
    $isRamadanWeek = $isRamadanWeek ?? false;

    $stmt = $pdo->prepare(
        "SELECT s.day_of_week, s.slot_number, s.room_code, COALESCE(s.extra_minutes,0) AS extra_minutes,
                c.course_name, c.course_type, c.subject_code, c.year_level,
                d.full_name AS doctor_name,
                COALESCE(dyc.color_code, d.color_code) AS color_code
         FROM doctor_schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN doctors d ON d.doctor_id = s.doctor_id
         LEFT JOIN doctor_year_colors dyc
           ON dyc.doctor_id = s.doctor_id AND dyc.year_level = c.year_level
         LEFT JOIN doctor_week_cancellations x
           ON x.week_id = s.week_id AND x.doctor_id = s.doctor_id AND x.day_of_week = s.day_of_week
         LEFT JOIN doctor_slot_cancellations xs
           ON xs.week_id = s.week_id AND xs.doctor_id = s.doctor_id AND xs.day_of_week = s.day_of_week AND xs.slot_number = s.slot_number
         WHERE s.week_id = :week_id
           AND x.cancellation_id IS NULL
           AND xs.slot_cancellation_id IS NULL
           AND s.counts_towards_hours = 1
           AND c.program = :program
           AND c.year_level = :year_level
           AND c.semester = :semester"
    );
    $stmt->execute([':week_id' => $weekId, ':program' => $program, ':year_level' => $yearLevel, ':semester' => $semester]);
    $rows = $stmt->fetchAll();

    // Combine (if multiple per slot => Multiple)
    $grid = [];
    foreach ($rows as $r) {
        $day = (string)$r['day_of_week'];
        $slot = (int)$r['slot_number'];
        if (!isset($grid[$day])) $grid[$day] = [];
        if (!isset($grid[$day][$slot])) {
            $grid[$day][$slot] = $r;
        } else {
            $grid[$day][$slot] = [
                'course_name' => 'Multiple',
                'course_type' => 'R',
                'subject_code' => '',
                'doctor_name' => '',
                'color_code' => '#999999',
                'year_level' => $yearLevel,
                'room_code' => null,
            ];
        }
    }

    $xlsx = new SimpleXlsxWriter();

    $title = "Student Schedule — {$program} — Year {$yearLevel} — Sem {$semester} — {$weekLabel}";

    $dataRows = [];
    $styleMap = [];
    $rowHeights = [];

    $dataRows[] = [$title, '', '', '', '', ''];
    $styleMap[] = [0 => $xlsx->styleTitle()];
    $rowHeights[] = 24;

    $hdr = array_merge(['Time'], $days);
    $dataRows[] = $hdr;
    $styleMap[] = array_fill(0, count($hdr), $xlsx->styleHeader());
    $rowHeights[] = 20;

    foreach ($slots as $slot) {
        // Show time only (no "Slot" prefix)
        $slotLabel = $isRamadanWeek
            ? match ($slot) {
                1 => '8:30 AM–9:40 AM',
                2 => '9:45 AM–10:55 AM',
                3 => '11:00 AM–12:10 PM',
                4 => '12:10 PM–1:20 PM',
                5 => '1:20 PM–2:30 PM',
                default => 'Time',
            }
            : match ($slot) {
                1 => '8:30 AM–10:00 AM',
                2 => '10:10 AM–11:30 AM',
                3 => '11:40 AM–1:00 PM',
                4 => '1:10 PM–2:40 PM',
                5 => '2:50 PM–4:20 PM',
                default => 'Time',
            };

        $row = [$slotLabel];
        $rowStyles = [0 => $xlsx->styleSlot()];

        foreach ($days as $d) {
            $isStripeRow = ($slot % 2 === 0);

            $text = '';
            $style = $isStripeRow ? $xlsx->styleStripe() : $xlsx->styleCell();

            if (isset($grid[$d][$slot])) {
                $r = $grid[$d][$slot];
                $text = (string)$r['course_name'];

                $courseType = (string)($r['course_type'] ?? '');
                $subjectCode = (string)($r['subject_code'] ?? '');
                $label = trim($courseType . ($subjectCode !== '' ? (' ' . $subjectCode) : ''));
                if ($label !== '') $text .= "\n" . $label;

                $extra = (int)($r['extra_minutes'] ?? 0);
                if ($extra > 0) $text .= "\n+" . $extra . "m";

                $room = (string)($r['room_code'] ?? '');
                if ($room !== '') $text .= "\nRoom: " . $room;

                if (!empty($r['doctor_name'])) {
                    $text .= "\n" . (string)$r['doctor_name'];
                }

                $hex = strtoupper(ltrim((string)($r['color_code'] ?? '#999999'), '#'));
                // Make doctor color fills much lighter for a cleaner Excel look.
                $style = $xlsx->styleLectureFill(XlsxColor::pastelize($hex, 0.85));
            }

            $row[] = $text;
            $rowStyles[] = $style;
        }

        $dataRows[] = $row;
        $styleMap[] = $rowStyles;
        $rowHeights[] = 64;
    }

    // Only set header heights. Let Excel auto-size schedule rows to avoid text being squeezed.
    $xlsx->addSheet(
        'Student Schedule',
        $dataRows,
        [
            'colWidths' => [0 => 28, 1 => 42, 2 => 42, 3 => 42, 4 => 42, 5 => 42],
            'rowHeights' => [0 => 28, 1 => 24],
            'merges' => ['A1:F1'],
            'styleMap' => $styleMap,
        ]
    );

    $fileName = preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $program) . " Year {$yearLevel} Sem {$semester} - {$weekLabel}.xlsx";
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
