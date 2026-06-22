<?php

declare(strict_types=1);

function dmportal_cairo_timezone(): DateTimeZone
{
    return new DateTimeZone('Africa/Cairo');
}

function dmportal_cairo_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', dmportal_cairo_timezone());
}

/**
 * @return array<int, array{start:string,end:string}>
 */
function dmportal_slot_time_ranges(bool $isRamadan = false): array
{
    if ($isRamadan) {
        return [
            1 => ['start' => '08:30:00', 'end' => '09:40:00'],
            2 => ['start' => '09:45:00', 'end' => '10:55:00'],
            3 => ['start' => '11:00:00', 'end' => '12:10:00'],
            4 => ['start' => '12:10:00', 'end' => '13:20:00'],
            5 => ['start' => '13:20:00', 'end' => '14:30:00'],
        ];
    }

    return [
        1 => ['start' => '08:30:00', 'end' => '10:00:00'],
        2 => ['start' => '10:10:00', 'end' => '11:30:00'],
        3 => ['start' => '11:40:00', 'end' => '13:00:00'],
        4 => ['start' => '13:10:00', 'end' => '14:40:00'],
        5 => ['start' => '14:50:00', 'end' => '16:20:00'],
    ];
}

function dmportal_day_offset_from_label(string $dayLabel): ?int
{
    static $map = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4];

    return $map[$dayLabel] ?? null;
}

/**
 * @return array{start:DateTimeImmutable,end:DateTimeImmutable,is_ramadan:bool,timezone:string}|null
 */
function dmportal_schedule_lecture_range_from_parts(
    string $weekStartDate,
    bool $isRamadan,
    string $dayOfWeek,
    int $slotNumber
): ?array {
    $offset = dmportal_day_offset_from_label($dayOfWeek);
    if ($offset === null || $slotNumber < 1 || $slotNumber > 5) {
        return null;
    }

    $ranges = dmportal_slot_time_ranges($isRamadan);
    $slotRange = $ranges[$slotNumber] ?? null;
    if (!$slotRange) {
        return null;
    }

    $tz = dmportal_cairo_timezone();
    $weekStart = new DateTimeImmutable($weekStartDate, $tz);
    $lectureDate = $weekStart->modify('+' . $offset . ' days');

    $start = new DateTimeImmutable(
        $lectureDate->format('Y-m-d') . ' ' . $slotRange['start'],
        $tz
    );
    $end = new DateTimeImmutable(
        $lectureDate->format('Y-m-d') . ' ' . $slotRange['end'],
        $tz
    );

    return [
        'start' => $start,
        'end' => $end,
        'is_ramadan' => $isRamadan,
        'timezone' => 'Africa/Cairo',
    ];
}

/**
 * @return array{start:DateTimeImmutable,end:DateTimeImmutable,is_ramadan:bool,timezone:string}|null
 */
function dmportal_schedule_lecture_range(PDO $pdo, int $scheduleId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.day_of_week, s.slot_number, w.start_date, w.is_ramadan
         FROM doctor_schedules s
         JOIN weeks w ON w.week_id = s.week_id
         WHERE s.schedule_id = :sid
         LIMIT 1'
    );
    $stmt->execute([':sid' => $scheduleId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['start_date'])) {
        return null;
    }

    return dmportal_schedule_lecture_range_from_parts(
        (string)$row['start_date'],
        (int)($row['is_ramadan'] ?? 0) === 1,
        (string)$row['day_of_week'],
        (int)$row['slot_number']
    );
}

function dmportal_lecture_window_state(
    DateTimeImmutable $nowCairo,
    DateTimeImmutable $start,
    DateTimeImmutable $end
): string {
    if ($nowCairo < $start) {
        return 'upcoming';
    }
    if ($nowCairo >= $end) {
        return 'ended';
    }

    return 'active';
}

function dmportal_is_within_lecture_window(
    DateTimeImmutable $ts,
    DateTimeImmutable $start,
    DateTimeImmutable $end
): bool {
    return $ts >= $start && $ts < $end;
}

function dmportal_schedule_window_state_from_meta(
    string $weekStartDate,
    bool $isRamadan,
    string $dayOfWeek,
    int $slotNumber,
    ?DateTimeImmutable $nowCairo = null
): string {
    $range = dmportal_schedule_lecture_range_from_parts($weekStartDate, $isRamadan, $dayOfWeek, $slotNumber);
    if (!$range) {
        return 'ended';
    }

    $now = $nowCairo ?? dmportal_cairo_now();

    return dmportal_lecture_window_state($now, $range['start'], $range['end']);
}
