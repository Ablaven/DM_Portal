<?php

declare(strict_types=1);

/**
 * Build year/semester label for hours report UI and exports.
 */
function dmportal_hours_report_year_sem_label(int $yearLevel, int $semester): string
{
    if ($yearLevel > 0 && $semester > 0) {
        return 'Year ' . $yearLevel . ' / Sem ' . $semester;
    }
    if ($yearLevel > 0) {
        return 'Year ' . $yearLevel . ' / All Semesters';
    }
    if ($semester > 0) {
        return 'All Years / Sem ' . $semester;
    }
    return 'All Years/Semesters';
}

/**
 * Academic year string (e.g. "2025/2026") from a date; Sep+ starts new year.
 */
function dmportal_hours_report_academic_year_from_date(DateTimeInterface $date): string
{
    $y = (int)$date->format('Y');
    $m = (int)$date->format('n');
    $startYear = ($m >= 9) ? $y : ($y - 1);
    return $startYear . '/' . ($startYear + 1);
}

/**
 * Fetch hours report grouped by doctor.
 *
 * @return list<array{doctor_id:int,full_name:string,courses:list<array>,totals:array{allocated_hours:float,done_hours:float,remaining_hours:float}}>
 */
function dmportal_fetch_hours_report(
    PDO $pdo,
    int $yearLevel,
    int $semester,
    int $doctorScopeId = 0,
    int $singleDoctorId = 0
): array {
    $touchTable = static function (string $table) use ($pdo): bool {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($stmt) {
                $stmt->fetch(PDO::FETCH_NUM);
            }
            if ($stmt) {
                $stmt->closeCursor();
            }
            return true;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1146) {
                return false;
            }
            throw $e;
        }
    };

    if (!$touchTable('course_doctors')) {
        return [];
    }

    $hasCourseDoctorHours = $touchTable('course_doctor_hours');
    $hasWeekCancellations = $touchTable('doctor_week_cancellations');
    $hasSlotCancellations = $touchTable('doctor_slot_cancellations');
    $hasSchedules = $touchTable('doctor_schedules');

    $hJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN course_doctor_hours h ON h.course_id = c.course_id AND h.doctor_id = d.doctor_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, NULL AS doctor_id, NULL AS allocated_hours) h ON 1=0';

    $weekCancelJoin = $hasWeekCancellations
        ? "LEFT JOIN doctor_week_cancellations cw\n            ON cw.week_id = s.week_id\n           AND cw.doctor_id = s.doctor_id\n           AND cw.day_of_week = s.day_of_week"
        : "LEFT JOIN (SELECT NULL AS cancellation_id, NULL AS week_id, NULL AS doctor_id, NULL AS day_of_week) cw ON 1=0";

    $slotCancelJoin = $hasSlotCancellations
        ? "LEFT JOIN doctor_slot_cancellations cs\n            ON cs.week_id = s.week_id\n           AND cs.doctor_id = s.doctor_id\n           AND cs.day_of_week = s.day_of_week\n           AND cs.slot_number = s.slot_number"
        : "LEFT JOIN (SELECT NULL AS slot_cancellation_id, NULL AS week_id, NULL AS doctor_id, NULL AS day_of_week, NULL AS slot_number) cs ON 1=0";

    $doneSubquery = $hasSchedules ? "
          SELECT
            s.doctor_id,
            s.course_id,
            COUNT(*) AS done_slots,
             SUM(COALESCE(s.extra_minutes,0)) AS done_extra_minutes
          FROM doctor_schedules s
          $weekCancelJoin
          $slotCancelJoin
          WHERE s.counts_towards_hours = 1
            AND cw.cancellation_id IS NULL
            AND cs.slot_cancellation_id IS NULL
          GROUP BY s.doctor_id, s.course_id
        " : "
          SELECT NULL AS doctor_id, NULL AS course_id, 0 AS done_slots, 0 AS done_extra_minutes
        ";

    $where = [];
    if ($yearLevel > 0) {
        $where[] = 'c.year_level = :year_level';
    }
    if ($semester > 0) {
        $where[] = 'c.semester = :semester';
    }
    if ($doctorScopeId > 0) {
        $where[] = 'd.doctor_id = :doctor_id';
    }
    if ($singleDoctorId > 0) {
        $where[] = 'd.doctor_id = :single_doctor_id';
    }
    $courseFilterSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

    $allocJoin = $hasCourseDoctorHours
        ? 'LEFT JOIN (SELECT course_id, COUNT(*) AS alloc_cnt FROM course_doctor_hours GROUP BY course_id) ha ON ha.course_id = c.course_id'
        : 'LEFT JOIN (SELECT NULL AS course_id, 0 AS alloc_cnt) ha ON 1=0';

    $sql = "
        SELECT
          d.doctor_id,
          d.full_name,
          c.course_id,
          c.course_name,
          c.course_type,
          c.subject_code,
          c.program,
          c.year_level,
          c.semester,
          c.total_hours,
          CASE
            WHEN COALESCE(ha.alloc_cnt, 0) > 0 THEN COALESCE(h.allocated_hours, 0)
            ELSE (COALESCE(c.total_hours, 0) / GREATEST(cd.cnt, 1))
          END AS allocated_hours,
          ROUND(COALESCE(s.done_slots, 0) * 1.5 + (COALESCE(s.done_extra_minutes,0) / 60), 2) AS done_hours
        FROM doctors d
        JOIN (
          SELECT doctor_id, COUNT(*) AS cnt
          FROM course_doctors
          GROUP BY doctor_id
        ) dc ON dc.doctor_id = d.doctor_id
        JOIN course_doctors x ON x.doctor_id = d.doctor_id
        JOIN courses c ON c.course_id = x.course_id$courseFilterSql
        LEFT JOIN (
          SELECT course_id, COUNT(*) AS cnt
          FROM course_doctors
          GROUP BY course_id
        ) cd ON cd.course_id = c.course_id
        $hJoin
        $allocJoin
        LEFT JOIN (
          $doneSubquery
        ) s ON s.doctor_id = d.doctor_id AND s.course_id = c.course_id
        ORDER BY d.full_name ASC, c.program ASC, c.year_level ASC, c.course_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = [];
    if ($yearLevel > 0) {
        $params[':year_level'] = $yearLevel;
    }
    if ($semester > 0) {
        $params[':semester'] = $semester;
    }
    if ($doctorScopeId > 0) {
        $params[':doctor_id'] = $doctorScopeId;
    }
    if ($singleDoctorId > 0) {
        $params[':single_doctor_id'] = $singleDoctorId;
    }
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $byDoctor = [];
    foreach ($rows as $r) {
        $docId = (int)$r['doctor_id'];
        if (!isset($byDoctor[$docId])) {
            $byDoctor[$docId] = [
                'doctor_id' => $docId,
                'full_name' => $r['full_name'],
                'courses' => [],
                'totals' => [
                    'allocated_hours' => 0.0,
                    'done_hours' => 0.0,
                    'remaining_hours' => 0.0,
                ],
            ];
        }

        $allocated = (float)$r['allocated_hours'];
        $done = (float)$r['done_hours'];
        $remaining = max(0.0, round($allocated - $done, 2));

        $byDoctor[$docId]['courses'][] = [
            'course_id' => (int)$r['course_id'],
            'course_name' => $r['course_name'],
            'course_type' => $r['course_type'],
            'subject_code' => $r['subject_code'],
            'program' => $r['program'] ?? null,
            'year_level' => isset($r['year_level']) ? (int)$r['year_level'] : null,
            'semester' => isset($r['semester']) ? (int)$r['semester'] : null,
            'allocated_hours' => round($allocated, 2),
            'done_hours' => round($done, 2),
            'remaining_hours' => $remaining,
        ];

        $byDoctor[$docId]['totals']['allocated_hours'] += $allocated;
        $byDoctor[$docId]['totals']['done_hours'] += $done;
        $byDoctor[$docId]['totals']['remaining_hours'] += $remaining;
    }

    $doctors = array_values($byDoctor);
    foreach ($doctors as &$d) {
        $d['totals']['allocated_hours'] = round((float)$d['totals']['allocated_hours'], 2);
        $d['totals']['done_hours'] = round((float)$d['totals']['done_hours'], 2);
        $d['totals']['remaining_hours'] = round((float)$d['totals']['remaining_hours'], 2);
    }
    unset($d);

    return $doctors;
}
