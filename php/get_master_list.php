<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

$yearLevel = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$weekId = isset($_GET['week_id']) ? (int)$_GET['week_id'] : 0;

function bad_request(string $m): void {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $m]);
  exit;
}

if ($yearLevel !== 0 && ($yearLevel < 1 || $yearLevel > 3)) bad_request('year_level must be 1-3 or empty.');
if ($semester !== 0 && ($semester < 1 || $semester > 2)) bad_request('semester must be 1-2 or empty.');

try {
  $pdo = get_pdo();

  $where = [];
  $params = [];

  if ($weekId > 0) {
    $where[] = 's.week_id = :week_id';
    $params[':week_id'] = $weekId;
  }
  if ($yearLevel > 0) {
    $where[] = 'c.year_level = :year_level';
    $params[':year_level'] = $yearLevel;
  }
  if ($semester > 0) {
    $where[] = 'c.semester = :semester';
    $params[':semester'] = $semester;
  }

  // Only count slots that:
  // - are marked counts_towards_hours=1
  // - are NOT cancelled (day or slot)
  $where[] = 's.counts_towards_hours = 1';
  $where[] = 'cw.cancellation_id IS NULL';
  $where[] = 'cs.slot_cancellation_id IS NULL';

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sql = "
    SELECT
      c.course_id,
      c.year_level,
      c.semester,
      c.course_type,
      c.subject_code,
      c.course_name,
      d.doctor_id,
      d.full_name AS doctor_name,
      COUNT(*) AS slots,
      ROUND(COUNT(*) * 1.5, 2) AS total_hours
    FROM doctor_schedules s
    JOIN courses c ON c.course_id = s.course_id
    JOIN doctors d ON d.doctor_id = s.doctor_id
    LEFT JOIN doctor_week_cancellations cw
      ON cw.week_id = s.week_id AND cw.doctor_id = s.doctor_id AND cw.day_of_week = s.day_of_week
    LEFT JOIN doctor_slot_cancellations cs
      ON cs.week_id = s.week_id AND cs.doctor_id = s.doctor_id AND cs.day_of_week = s.day_of_week AND cs.slot_number = s.slot_number
    $whereSql
    GROUP BY c.course_id, c.year_level, c.semester, c.course_type, c.subject_code, c.course_name, d.doctor_id, d.full_name
    ORDER BY c.year_level ASC, c.semester ASC, c.course_type ASC, c.subject_code ASC, c.course_name ASC, d.full_name ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  echo json_encode(['success' => true, 'data' => ['items' => $stmt->fetchAll()]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to fetch master list.']);
}
