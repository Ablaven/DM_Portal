<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';

auth_require_roles(['admin','management'], true);
require_once __DIR__ . '/_course_hours_helpers.php';

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'course_id is required.']);
  exit;
}

try {
  $pdo = get_pdo();

  // If table missing, return empty allocations (feature not initialized yet)
  try {
    $pdo->exec('SELECT 1 FROM course_doctor_hours LIMIT 1');
  } catch (PDOException $e) { 
    if ((int)($e->errorInfo[1] ?? 0) === 1146) {
      echo json_encode(['success'=>true,'data'=>['course_id'=>$courseId,'allocations'=>[]]]);
      exit;
    }
    throw $e;
  }

  $stmt = $pdo->prepare('SELECT doctor_id, allocated_hours FROM course_doctor_hours WHERE course_id = :course_id ORDER BY doctor_id ASC');
  $stmt->execute([':course_id' => $courseId]);

  echo json_encode(['success'=>true,'data'=>['course_id'=>$courseId,'allocations'=>$stmt->fetchAll()]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Failed to fetch course doctor hours.']);
}
