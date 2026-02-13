<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_xlsx_writer.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';
require_once __DIR__ . '/_smtp_mailer.php';

header('Content-Type: application/json');

auth_require_roles(['admin', 'management'], true);

$input = json_decode(file_get_contents('php://input') ?: '{}', true);

$program = trim((string)($input['program'] ?? ''));
$yearLevel = (int)($input['year_level'] ?? 0);
$semester = (int)($input['semester'] ?? 0);
$weekId = (int)($input['week_id'] ?? 0);

if ($program === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'program is required.']);
    exit;
}

if ($yearLevel < 1 || $yearLevel > 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'year_level must be 1-3.']);
    exit;
}

if ($semester < 1 || $semester > 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'semester must be 1 or 2.']);
    exit;
}

try {
    $pdo = get_pdo();

    $defaultCc = ['asmaa.sharif@ufe.edu.eg', 'Sherrost@yahoo.com'];
    $emailStmt = $pdo->prepare(
        'SELECT DISTINCT email FROM students WHERE program = :program AND year_level = :year_level AND email IS NOT NULL AND TRIM(email) <> "" ORDER BY full_name ASC'
    );
    $emailStmt->execute([':program' => $program, ':year_level' => $yearLevel]);
    $emails = array_values(array_filter(array_map('trim', array_column($emailStmt->fetchAll(), 'email'))));

    if (empty($emails)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No student emails found for this year level.']);
        exit;
    }

    $recipient = array_shift($emails);
    $cc = array_values(array_unique(array_filter(array_merge($emails, $defaultCc), function ($email) use ($recipient) {
        $value = trim((string)$email);
        return $value !== '' && $value !== $recipient;
    })));

    dmportal_ensure_doctor_year_colors_table($pdo);

    if ($weekId <= 0) {
        $wk = $pdo->query("SELECT week_id, label FROM weeks WHERE status='active' ORDER BY week_id DESC LIMIT 1")->fetch();
        if (!$wk) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No active week']);
            exit;
        }
        $weekId = (int)$wk['week_id'];
        $weekLabel = (string)$wk['label'];
    } else {
        $wkStmt = $pdo->prepare('SELECT week_id, label FROM weeks WHERE week_id = :id');
        $wkStmt->execute([':id' => $weekId]);
        $wk = $wkStmt->fetch();
        $weekLabel = $wk ? (string)$wk['label'] : ('Week ' . $weekId);
    }

    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
    $slots = [1, 2, 3, 4, 5];

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
                'extra_minutes' => 0,
            ];
        }
    }

    $xlsx = new SimpleXlsxWriter();
    $title = "Student Schedule — {$program} — Year {$yearLevel} — Sem {$semester} — {$weekLabel}";

    $dataRows = [];
    $styleMap = [];

    $dataRows[] = [$title, '', '', '', '', ''];
    $styleMap[] = [0 => $xlsx->styleTitle()];

    $hdr = array_merge(['Time'], $days);
    $dataRows[] = $hdr;
    $styleMap[] = array_fill(0, count($hdr), $xlsx->styleHeader());

    foreach ($slots as $slot) {
        $slotLabel = match ($slot) {
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
                $style = $xlsx->styleLectureFill(XlsxColor::pastelize($hex, 0.85));
            }

            $row[] = $text;
            $rowStyles[] = $style;
        }

        $dataRows[] = $row;
        $styleMap[] = $rowStyles;
    }

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
    $xlsxBytes = $xlsx->downloadToString($fileName);

    $subject = "Student Schedule — Year {$yearLevel} — Sem {$semester} — {$weekLabel}";
    $body = "Dear Students,\n\nPlease find attached the schedule for {$program} (Year {$yearLevel}, Semester {$semester}) for {$weekLabel}." .
        "\n\nIf you have any questions or require clarification, please contact the Academic Office." .
        "\n\nKind regards,\nDigital Marketing Portal";

    $mailer = new DmportalSmtpMailer();
    $mailer->send(
        $recipient,
        $subject,
        $body,
        [[
            'name' => $fileName,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'data' => $xlsxBytes,
        ]],
        $cc
    );

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
