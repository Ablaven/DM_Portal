<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);
require_once __DIR__ . '/_course_hours_helpers.php';

function bad_request(string $m): void { http_response_code(400); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Method not allowed. Use POST.']);
  exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$allocRaw = trim((string)($_POST['allocations'] ?? '')); // JSON: [{doctor_id, hours}]

if ($courseId <= 0) bad_request('course_id is required.');
if ($allocRaw === '') bad_request('allocations is required.');

$alloc = json_decode($allocRaw, true);
if (!is_array($alloc)) bad_request('allocations must be valid JSON array.');

$allocations = [];
foreach ($alloc as $row) {
  $did = (int)($row['doctor_id'] ?? 0);
  $hrs = (float)($row['hours'] ?? -1);
  if ($did <= 0) bad_request('Invalid doctor_id in allocations.');
  if ($hrs < 0 || $hrs > 500) bad_request('Invalid hours in allocations.');
  $allocations[] = ['doctor_id' => $did, 'hours' => round($hrs, 2)];
}

// de-dup by doctor_id
$tmp = [];
foreach ($allocations as $a) { $tmp[(string)$a['doctor_id']] = $a; }
$allocations = array_values($tmp);

try {
  $pdo = get_pdo();

  // Ensure table exists (safe for older DBs)
  dmportal_ensure_course_doctor_hours_table($pdo);

  /*
   * NOTE: do not run DDL inside a transaction.
   * dmportal_ensure_course_doctor_hours_table runs outside of a transaction.
   */


  // course exists?
  $chk = $pdo->prepare('SELECT total_hours FROM courses WHERE course_id = :id');
  $chk->execute([':id' => $courseId]);
  $course = $chk->fetch();
  if (!$course) {
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Course not found.']);
    exit;
  }

  // Validate doctors are assigned to this course (via course_doctors)
  $stmt = $pdo->prepare('SELECT doctor_id FROM course_doctors WHERE course_id = :course_id');
  $stmt->execute([':course_id' => $courseId]);
  $assigned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

  if (!$assigned) {
    bad_request('No doctors are assigned to this course. Assign doctors first.');
  }

  $assignedSet = array_fill_keys(array_map('strval', $assigned), true);
  foreach ($allocations as $a) {
    if (!isset($assignedSet[(string)$a['doctor_id']])) {
      bad_request('Allocations include a doctor that is not assigned to this course.');
    }
  }

  $totalHours = (float)$course['total_hours'];
  $sum = 0.0;
  foreach ($allocations as $a) { $sum += (float)$a['hours']; }
  $sum = round($sum, 2);

  if (round($totalHours, 2) !== $sum) {
    bad_request('Allocated hours must sum exactly to the course total hours (' . round($totalHours, 2) . ').');
  }

  // IMPORTANT: Don't run DDL inside a transaction (implicit commit). We already ensured table exists.
  $pdo->beginTransaction();

  // Remove existing allocations for this course
  $pdo->prepare('DELETE FROM course_doctor_hours WHERE course_id = :course_id')->execute([':course_id' => $courseId]);

  $ins = $pdo->prepare('INSERT INTO course_doctor_hours (course_id, doctor_id, allocated_hours) VALUES (:course_id, :doctor_id, :hours)');
  foreach ($allocations as $a) {
    $ins->execute([':course_id' => $courseId, ':doctor_id' => $a['doctor_id'], ':hours' => $a['hours']]);
  }

  $pdo->commit();
  echo json_encode(['success'=>true,'data'=>['saved'=>true,'course_id'=>$courseId]]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Failed to set course doctor hours.']);
}
