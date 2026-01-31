<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);

function bad_request(string $m): void { http_response_code(400); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Method not allowed. Use POST.']);
  exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$doctorIdsRaw = trim((string)($_POST['doctor_ids'] ?? '')); // comma-separated

if ($courseId <= 0) bad_request('course_id is required.');

$doctorIds = [];
if ($doctorIdsRaw !== '') {
  foreach (preg_split('/\s*,\s*/', $doctorIdsRaw) as $p) {
    if ($p === '') continue;
    if (!ctype_digit($p)) bad_request('doctor_ids must be a comma-separated list of integers.');
    $doctorIds[] = (int)$p;
  }
}
$doctorIds = array_values(array_unique(array_filter($doctorIds, fn($x) => $x > 0)));

try {
  $pdo = get_pdo();

  // course exists?
  $chk = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = :id');
  $chk->execute([':id'=>$courseId]);
  if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Course not found.']);
    exit;
  }

  if ($doctorIds) {
    $in = implode(',', array_fill(0, count($doctorIds), '?'));
    $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_id IN ($in)");
    $stmt->execute($doctorIds);
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    if (count($found) !== count($doctorIds)) {
      bad_request('One or more doctor_ids do not exist.');
    }
  }

  // Ensure allocations table exists (best-effort). Do BEFORE transaction because DDL auto-commits.
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS course_doctor_hours (\n"
      ."  course_id BIGINT UNSIGNED NOT NULL,\n"
      ."  doctor_id BIGINT UNSIGNED NOT NULL,\n"
      ."  allocated_hours DECIMAL(6,2) NOT NULL DEFAULT 0,\n"
      ."  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
      ."  PRIMARY KEY (course_id, doctor_id)\n"
      .") ENGINE=InnoDB"
    );
  } catch (Throwable $e) {
    // ignore if permissions block DDL
  }

  $pdo->beginTransaction();

  // Remove doctor mappings
  $pdo->prepare('DELETE FROM course_doctors WHERE course_id = :course_id')->execute([':course_id'=>$courseId]);

  // Remove allocations for this course (will be re-entered via Split Hours modal)
  try {
    $pdo->prepare('DELETE FROM course_doctor_hours WHERE course_id = :course_id')->execute([':course_id'=>$courseId]);
  } catch (Throwable $e) {
    // ignore if table missing
  }

  if ($doctorIds) {
    $ins = $pdo->prepare('INSERT INTO course_doctors (course_id, doctor_id) VALUES (:course_id, :doctor_id)');
    foreach ($doctorIds as $did) {
      $ins->execute([':course_id'=>$courseId, ':doctor_id'=>$did]);
    }
  }

  // Keep legacy single doctor_id column aligned with the first doctor in list (or NULL)
  $legacyDoctorId = $doctorIds[0] ?? null;
  $pdo->prepare('UPDATE courses SET doctor_id = :doctor_id WHERE course_id = :course_id')
      ->execute([':doctor_id'=>$legacyDoctorId, ':course_id'=>$courseId]);

  $pdo->commit();
  echo json_encode(['success'=>true,'data'=>['saved'=>true,'doctor_ids'=>$doctorIds]]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Failed to set course doctors.']);
}
