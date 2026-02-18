<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

auth_require_login(false);
$u = auth_current_user();
if (($u['role'] ?? '') !== 'admin') {
    auth_require_roles(['teacher']);
}
require_once __DIR__ . '/_xlsx_writer.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

// XLSX export for a single doctor's week schedule.

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

// Teacher can only export their own schedule
if (($u['role'] ?? '') === 'teacher') {
    $doctorId = (int)($u['doctor_id'] ?? 0);
}
$weekId = isset($_GET['week_id']) ? (int)$_GET['week_id'] : 0;

if ($doctorId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'doctor_id is required';
    exit;
}

try {
    $pdo = get_pdo();

    // Ensure optional per-year doctor color table exists.
    dmportal_ensure_doctor_year_colors_table($pdo);

    $dStmt = $pdo->prepare('SELECT doctor_id, full_name, color_code FROM doctors WHERE doctor_id = :id');
    $dStmt->execute([':id' => $doctorId]);
    $doctor = $dStmt->fetch();
    if (!$doctor) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Doctor not found';
        exit;
    }

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);

    if ($weekId <= 0) {
        $stmt = $pdo->prepare("SELECT week_id, label, start_date, is_ramadan FROM weeks WHERE status='active' AND term_id = :term_id ORDER BY week_id DESC LIMIT 1");
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
        $weekStartDate = !empty($wk['start_date']) ? (string)$wk['start_date'] : null;
        $isRamadanWeek = (int)($wk['is_ramadan'] ?? 0) === 1;
    } else {
        $wkStmt = $pdo->prepare('SELECT week_id, label, start_date, is_ramadan FROM weeks WHERE week_id = :id');
        $wkStmt->execute([':id' => $weekId]);
        $wk = $wkStmt->fetch();
        $weekLabel = $wk ? (string)$wk['label'] : ('Week ' . $weekId);
        $weekStartDate = ($wk && !empty($wk['start_date'])) ? (string)$wk['start_date'] : null;
        $isRamadanWeek = ($wk && (int)($wk['is_ramadan'] ?? 0) === 1);
    }

    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
    $dayOffsets = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4];
    $slots = [1, 2, 3, 4, 5];
    $isRamadanWeek = $isRamadanWeek ?? false;

    $regularSlotTimes = [
        1 => ['08:30:00', '10:00:00'],
        2 => ['10:10:00', '11:30:00'],
        3 => ['11:40:00', '13:00:00'],
        4 => ['13:10:00', '14:40:00'],
        5 => ['14:50:00', '16:20:00'],
    ];
    $ramadanSlotTimes = [
        1 => ['08:30:00', '09:40:00'],
        2 => ['09:40:00', '10:50:00'],
        3 => ['11:00:00', '12:10:00'],
        4 => ['12:10:00', '13:20:00'],
        5 => ['13:20:00', '14:30:00'],
    ];
    $slotTimes = $isRamadanWeek ? $ramadanSlotTimes : $regularSlotTimes;

    // Load cancellations for display
    $dayCancelStmt = $pdo->prepare('SELECT day_of_week, reason FROM doctor_week_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id');
    $dayCancelStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
    $cancelledDays = [];
    foreach ($dayCancelStmt->fetchAll() as $r) {
        $cancelledDays[(string)$r['day_of_week']] = (string)($r['reason'] ?? '');
    }

    $slotCancelStmt = $pdo->prepare('SELECT day_of_week, slot_number, reason FROM doctor_slot_cancellations WHERE week_id = :week_id AND doctor_id = :doctor_id');
    $slotCancelStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
    $cancelledSlots = [];
    foreach ($slotCancelStmt->fetchAll() as $r) {
        $d = (string)$r['day_of_week'];
        $s = (int)$r['slot_number'];
        if (!isset($cancelledSlots[$d])) $cancelledSlots[$d] = [];
        $cancelledSlots[$d][$s] = (string)($r['reason'] ?? '');
    }

    // Load doctor unavailability within the week (optional table)
    $unavailableSlots = [];
    if (!empty($weekStartDate)) {
        $weekStart = new DateTimeImmutable($weekStartDate . ' 00:00:00');
        $weekEnd = $weekStart->modify('+7 days');
        try {
            $uStmt = $pdo->prepare(
                'SELECT start_datetime, end_datetime, reason
                 FROM doctor_unavailability
                 WHERE doctor_id = :doctor_id
                   AND start_datetime < :end_dt
                   AND end_datetime > :start_dt'
            );
            $uStmt->execute([
                ':doctor_id' => $doctorId,
                ':start_dt' => $weekStart->format('Y-m-d H:i:s'),
                ':end_dt' => $weekEnd->format('Y-m-d H:i:s'),
            ]);

            foreach ($uStmt->fetchAll() as $u) {
                $uStart = new DateTimeImmutable((string)$u['start_datetime']);
                $uEnd = new DateTimeImmutable((string)$u['end_datetime']);
                $reason = (string)($u['reason'] ?? '');

                foreach ($days as $day) {
                    $offset = $dayOffsets[$day] ?? 0;
                    $dayDate = $weekStart->modify('+' . $offset . ' days')->format('Y-m-d');
                    foreach ($slots as $slotNum) {
                        [$tStart, $tEnd] = $slotTimes[$slotNum];
                        $slotStart = new DateTimeImmutable($dayDate . ' ' . $tStart);
                        $slotEnd = new DateTimeImmutable($dayDate . ' ' . $tEnd);
                        if ($uStart < $slotEnd && $uEnd > $slotStart) {
                            if (!isset($unavailableSlots[$day])) $unavailableSlots[$day] = [];
                            $unavailableSlots[$day][$slotNum] = $reason;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            // ignore if table missing
        }
    }

    // Exclude cancelled days + cancelled slots (same as UI logic)
    $sStmt = $pdo->prepare(
        "SELECT s.day_of_week, s.slot_number, s.room_code, COALESCE(s.extra_minutes,0) AS extra_minutes,
                c.course_name, c.course_type, c.subject_code, c.year_level,
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
         WHERE s.week_id = :week_id AND s.doctor_id = :doctor_id
           AND x.cancellation_id IS NULL
           AND xs.slot_cancellation_id IS NULL"
    );
    $sStmt->execute([':week_id' => $weekId, ':doctor_id' => $doctorId]);
    $rows = $sStmt->fetchAll();

    $grid = [];
    foreach ($rows as $r) {
        $grid[$r['day_of_week']][(int)$r['slot_number']] = $r;
    }

    $docName = (string)$doctor['full_name'];

    $xlsx = new SimpleXlsxWriter();

    $termLabel = $termId > 0 ? " — Term {$termId}" : '';
    $title = "{$docName}{$termLabel} — {$weekLabel}";

    $dataRows = [];
    $styleMap = [];
    $rowHeights = [];

    // Row 1: title
    $dataRows[] = [$title, '', '', '', '', ''];
    $styleMap[] = [0 => $xlsx->styleTitle()];
    $rowHeights[] = 24;

    // Row 2: header (include dates if week start_date exists)
    $dayHeaders = [];
    $weekStart = null;
    if (!empty($weekStartDate)) {
        try { $weekStart = new DateTimeImmutable($weekStartDate . ' 00:00:00'); } catch (Throwable $e) { $weekStart = null; }
    }
    foreach ($days as $day) {
        if ($weekStart) {
            $offset = $dayOffsets[$day] ?? 0;
            $dayDate = $weekStart->modify('+' . $offset . ' days')->format('d/m');
            $dayHeaders[] = $day . "\n" . $dayDate;
        } else {
            $dayHeaders[] = $day;
        }
    }

    $hdr = array_merge(['Time'], $dayHeaders);
    $dataRows[] = $hdr;
    $styleMap[] = array_fill(0, count($hdr), $xlsx->styleHeader());
    $rowHeights[] = 20;

    foreach ($slots as $slot) {
        // Show time only (no "Slot" prefix)
        $slotLabel = $isRamadanWeek
            ? match ($slot) {
                1 => '8:30 AM–9:40 AM',
                2 => '9:40 AM–10:50 AM',
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

        $isStripeRow = ($slot % 2 === 0);

        foreach ($days as $d) {
            $cellText = '';
            if (isset($cancelledDays[$d])) {
                $reason = trim((string)$cancelledDays[$d]);
                $cellText = 'CANCELLED' . ($reason !== '' ? ("\n" . $reason) : '');
                $rowStyles[] = $xlsx->styleCancelled();
            } elseif (isset($cancelledSlots[$d]) && isset($cancelledSlots[$d][$slot])) {
                $reason = trim((string)$cancelledSlots[$d][$slot]);
                $cellText = 'CANCELLED' . ($reason !== '' ? ("\n" . $reason) : '');
                $rowStyles[] = $xlsx->styleCancelled();
            } elseif (isset($unavailableSlots[$d]) && isset($unavailableSlots[$d][$slot])) {
                $reason = trim((string)$unavailableSlots[$d][$slot]);
                $cellText = 'UNAVAILABLE' . ($reason !== '' ? ("\n" . $reason) : '');
                $rowStyles[] = $xlsx->styleUnavailable();
            } elseif (isset($grid[$d][$slot])) {
                $r = $grid[$d][$slot];
                $courseName = (string)$r['course_name'];
                $courseType = (string)$r['course_type'];
                $subjectCode = (string)($r['subject_code'] ?? '');
                $label = trim($courseType . ($subjectCode !== '' ? (' ' . $subjectCode) : ''));
                $room = (string)($r['room_code'] ?? '');
                $extra = (int)($r['extra_minutes'] ?? 0);

                // Text layout (most important first)
                $cellText = $courseName;
                if ($label !== '') $cellText .= "\n" . $label;
                if ($extra > 0) $cellText .= "\n+" . $extra . "m";
                if ($room !== '') $cellText .= "\nRoom: " . $room;

                $hex = strtoupper(ltrim((string)($r['color_code'] ?? ($doctor['color_code'] ?? '#0055A4')), '#'));
                // Make doctor color fills much lighter for a cleaner Excel look.
                $rowStyles[] = $xlsx->styleLectureFill(XlsxColor::pastelize($hex, 0.85));
            } else {
                $rowStyles[] = $isStripeRow ? $xlsx->styleStripe() : $xlsx->styleCell();
            }
            $row[] = $cellText;
        }

        $dataRows[] = $row;
        $styleMap[] = $rowStyles;
        $rowHeights[] = 60;
    }

    // Only set header heights. Let Excel auto-size schedule rows to avoid text being squeezed.
    $xlsx->addSheet(
        'Schedule',
        $dataRows,
        [
            'colWidths' => [0 => 28, 1 => 40, 2 => 40, 3 => 40, 4 => 40, 5 => 40],
            'rowHeights' => [0 => 28, 1 => 34],
            'merges' => ['A1:F1'],
            'styleMap' => $styleMap,
        ]
    );

    $termSuffix = $termId > 0 ? " Term {$termId}" : '';
    $fileName = preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $docName) . "{$termSuffix} - {$weekLabel}.xlsx";
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed';
}
