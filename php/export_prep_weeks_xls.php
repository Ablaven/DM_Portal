<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';

// Admin-only: export all prep weeks for a term
auth_require_roles(['admin'], true);

require_once __DIR__ . '/_xlsx_writer.php';
require_once __DIR__ . '/_doctor_year_colors_helpers.php';

function bad_request(string $msg): void {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
}

try {
    $pdo = get_pdo();
    dmportal_ensure_doctor_year_colors_table($pdo);

    $termId = dmportal_get_term_id_from_request($pdo, $_GET);
    $termSemStmt = $pdo->prepare('SELECT semester FROM terms WHERE term_id = :id LIMIT 1');
    $termSemStmt->execute([':id' => $termId]);
    $termSemester = (int)($termSemStmt->fetchColumn() ?: 0);

    // Get all prep weeks for this term
    $stmt = $pdo->prepare("SELECT week_id, label, start_date, end_date, is_ramadan FROM weeks WHERE is_prep = 1 AND term_id = :term_id ORDER BY start_date ASC");
    $stmt->execute([':term_id' => $termId]);
    $prepWeeks = $stmt->fetchAll();

    if (empty($prepWeeks)) {
        bad_request('No prep weeks found for this term');
    }

    // Get all doctors
    $doctorStmt = $pdo->prepare('SELECT doctor_id, full_name, color_code FROM doctors ORDER BY full_name ASC');
    $doctorStmt->execute();
    $doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($doctors)) {
        bad_request('No doctors found');
    }

    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
    $dayOffsets = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4];
    $slots = [1, 2, 3, 4, 5];

    $regularSlotTimes = [
        1 => ['08:30:00', '10:00:00'],
        2 => ['10:10:00', '11:30:00'],
        3 => ['11:40:00', '13:00:00'],
        4 => ['13:10:00', '14:40:00'],
        5 => ['14:50:00', '16:20:00'],
    ];
    $ramadanSlotTimes = [
        1 => ['08:30:00', '09:40:00'],
        2 => ['09:45:00', '10:55:00'],
        3 => ['11:00:00', '12:10:00'],
        4 => ['12:10:00', '13:20:00'],
        5 => ['13:20:00', '14:30:00'],
    ];

    $xlsx = new SimpleXlsxWriter();
    $termLabel = $termSemester > 0 ? " — Sem {$termSemester}" : '';

    // Create a sheet for each prep week
    foreach ($prepWeeks as $week) {
        $weekId = (int)$week['week_id'];
        $weekLabel = dmportal_week_label_with_range($week, $weekId);
        $weekStartDate = !empty($week['start_date']) ? (string)$week['start_date'] : null;
        $isRamadanWeek = (int)($week['is_ramadan'] ?? 0) === 1;
        $slotTimes = $isRamadanWeek ? $ramadanSlotTimes : $regularSlotTimes;

        // Load all schedules for this prep week
        $sStmt = $pdo->prepare(
            "SELECT s.day_of_week, s.slot_number, s.room_code, s.doctor_id,
                    COALESCE(s.extra_minutes,0) AS extra_minutes,
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
             ORDER BY s.day_of_week, s.slot_number"
        );
        $sStmt->execute([':week_id' => $weekId]);
        $schedules = $sStmt->fetchAll();

        // Load cancellations
        $dayCancelStmt = $pdo->prepare('SELECT doctor_id, day_of_week, reason FROM doctor_week_cancellations WHERE week_id = :week_id');
        $dayCancelStmt->execute([':week_id' => $weekId]);
        $cancelledDays = [];
        foreach ($dayCancelStmt->fetchAll() as $r) {
            $docId = (int)$r['doctor_id'];
            $day = (string)$r['day_of_week'];
            if (!isset($cancelledDays[$docId])) $cancelledDays[$docId] = [];
            $cancelledDays[$docId][$day] = (string)($r['reason'] ?? '');
        }

        $slotCancelStmt = $pdo->prepare('SELECT doctor_id, day_of_week, slot_number, reason FROM doctor_slot_cancellations WHERE week_id = :week_id');
        $slotCancelStmt->execute([':week_id' => $weekId]);
        $cancelledSlots = [];
        foreach ($slotCancelStmt->fetchAll() as $r) {
            $docId = (int)$r['doctor_id'];
            $day = (string)$r['day_of_week'];
            $slot = (int)$r['slot_number'];
            if (!isset($cancelledSlots[$docId])) $cancelledSlots[$docId] = [];
            if (!isset($cancelledSlots[$docId][$day])) $cancelledSlots[$docId][$day] = [];
            $cancelledSlots[$docId][$day][$slot] = (string)($r['reason'] ?? '');
        }

        // Load unavailability for all doctors
        $unavailableSlots = [];
        if (!empty($weekStartDate)) {
            $weekStart = new DateTimeImmutable($weekStartDate . ' 00:00:00');
            $weekEnd = $weekStart->modify('+7 days');
            try {
                $uStmt = $pdo->prepare(
                    'SELECT doctor_id, start_datetime, end_datetime, reason
                     FROM doctor_unavailability
                     WHERE start_datetime < :end_dt
                       AND end_datetime > :start_dt'
                );
                $uStmt->execute([
                    ':start_dt' => $weekStart->format('Y-m-d H:i:s'),
                    ':end_dt' => $weekEnd->format('Y-m-d H:i:s'),
                ]);

                foreach ($uStmt->fetchAll() as $u) {
                    $docId = (int)$u['doctor_id'];
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
                                if (!isset($unavailableSlots[$docId])) $unavailableSlots[$docId] = [];
                                if (!isset($unavailableSlots[$docId][$day])) $unavailableSlots[$docId][$day] = [];
                                $unavailableSlots[$docId][$day][$slotNum] = $reason;
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                // ignore if table missing
            }
        }

        // Build grid by doctor
        $grid = [];
        foreach ($schedules as $r) {
            $docId = (int)$r['doctor_id'];
            $day = (string)$r['day_of_week'];
            $slot = (int)$r['slot_number'];
            if (!isset($grid[$docId])) $grid[$docId] = [];
            if (!isset($grid[$docId][$day])) $grid[$docId][$day] = [];
            $grid[$docId][$day][$slot] = $r;
        }

        $dataRows = [];
        $styleMap = [];
        $rowHeights = [];

        // Row 1: title
        $title = "Prep Week Schedule{$termLabel} — {$weekLabel}";
        $dataRows[] = [$title, '', '', '', '', ''];
        $styleMap[] = [0 => $xlsx->styleTitle()];
        $rowHeights[] = 24;

        // Row 2: day headers with dates
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

        $hdr = array_merge(['Doctor / Time'], $dayHeaders);
        $dataRows[] = $hdr;
        $styleMap[] = array_fill(0, count($hdr), $xlsx->styleHeader());
        $rowHeights[] = 20;

        // For each doctor, add a section
        foreach ($doctors as $doctor) {
            $doctorId = (int)$doctor['doctor_id'];
            $docName = (string)$doctor['full_name'];

            // Doctor name row (merged across all columns)
            $dataRows[] = [$docName, '', '', '', '', ''];
            $styleMap[] = [0 => $xlsx->styleHeaderSmall()];
            $rowHeights[] = 22;

            // Slot rows for this doctor
            foreach ($slots as $slot) {
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
                $isStripeRow = ($slot % 2 === 0);

                foreach ($days as $d) {
                    $cellText = '';
                    if (isset($cancelledDays[$doctorId]) && isset($cancelledDays[$doctorId][$d])) {
                        $reason = trim((string)$cancelledDays[$doctorId][$d]);
                        $cellText = 'CANCELLED' . ($reason !== '' ? ("\n" . $reason) : '');
                        $rowStyles[] = $xlsx->styleCancelled();
                    } elseif (isset($cancelledSlots[$doctorId][$d]) && isset($cancelledSlots[$doctorId][$d][$slot])) {
                        $reason = trim((string)$cancelledSlots[$doctorId][$d][$slot]);
                        $cellText = 'CANCELLED' . ($reason !== '' ? ("\n" . $reason) : '');
                        $rowStyles[] = $xlsx->styleCancelled();
                    } elseif (isset($unavailableSlots[$doctorId][$d]) && isset($unavailableSlots[$doctorId][$d][$slot])) {
                        $reason = trim((string)$unavailableSlots[$doctorId][$d][$slot]);
                        $cellText = 'UNAVAILABLE' . ($reason !== '' ? ("\n" . $reason) : '');
                        $rowStyles[] = $xlsx->styleUnavailable();
                    } elseif (isset($grid[$doctorId][$d][$slot])) {
                        $r = $grid[$doctorId][$d][$slot];
                        $courseName = (string)$r['course_name'];
                        $courseType = (string)$r['course_type'];
                        $subjectCode = (string)($r['subject_code'] ?? '');
                        $label = trim($courseType . ($subjectCode !== '' ? (' ' . $subjectCode) : ''));
                        $room = (string)($r['room_code'] ?? '');
                        $extra = (int)($r['extra_minutes'] ?? 0);

                        $cellText = $courseName;
                        if ($label !== '') $cellText .= "\n" . $label;
                        if ($extra > 0) $cellText .= "\n+" . $extra . "m";
                        if ($room !== '') $cellText .= "\nRoom: " . $room;

                        $hex = strtoupper(ltrim((string)($r['color_code'] ?? ($doctor['color_code'] ?? '#0055A4')), '#'));
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
        }

        // Add sheet
        $sheetName = mb_substr(preg_replace('/[^a-zA-Z0-9\-_ ]+/', '', $weekLabel), 0, 31);
        $xlsx->addSheet(
            $sheetName,
            $dataRows,
            [
                'colWidths' => [0 => 28, 1 => 40, 2 => 40, 3 => 40, 4 => 40, 5 => 40],
                'rowHeights' => [0 => 28, 1 => 34],
                'merges' => ['A1:F1'],
                'styleMap' => $styleMap,
            ]
        );
    }

    $termSuffix = $termSemester > 0 ? " Sem {$termSemester}" : '';
    $fileName = "Prep Weeks Schedule{$termSuffix}.xlsx";
    $xlsx->download($fileName);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed: ' . $e->getMessage();
}
