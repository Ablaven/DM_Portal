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

$sourceWeekId = (int)($_POST['source_week_id'] ?? 0);
$targetWeekId = (int)($_POST['target_week_id'] ?? 0);
$mode = trim((string)($_POST['mode'] ?? 'fill_empty')); // fill_empty | overwrite

if ($sourceWeekId <= 0) bad_request('source_week_id is required.');
if ($targetWeekId <= 0) bad_request('target_week_id is required.');
if (!in_array($mode, ['fill_empty','overwrite'], true)) bad_request('Invalid mode.');

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();

    // Weeks exist?
    $wk = $pdo->prepare('SELECT week_id FROM weeks WHERE week_id = :id');
    $wk->execute([':id'=>$sourceWeekId]);
    if (!$wk->fetch()) bad_request('Source week not found.');
    $wk->execute([':id'=>$targetWeekId]);
    if (!$wk->fetch()) bad_request('Target week not found.');

    // If overwrite, clear target schedules first
    if ($mode === 'overwrite') {
        $del = $pdo->prepare('DELETE FROM doctor_schedules WHERE week_id = :wid');
        $del->execute([':wid' => $targetWeekId]);
    }

    // Copy schedules. If fill_empty, skip slots that already exist in target.
    // room_code column may or may not exist; attempt with it and fallback.
    $hasRoom = true;
    try {
        $pdo->query('SELECT room_code FROM doctor_schedules LIMIT 1');
    } catch (PDOException $e) {
        $hasRoom = false;
    }

    if ($hasRoom) {
        $src = $pdo->prepare('SELECT doctor_id, course_id, day_of_week, slot_number, room_code FROM doctor_schedules WHERE week_id = :wid');
        $src->execute([':wid' => $sourceWeekId]);
        $rows = $src->fetchAll();

        $ins = $pdo->prepare('INSERT INTO doctor_schedules (week_id, doctor_id, course_id, day_of_week, slot_number, room_code) VALUES (:week_id, :doctor_id, :course_id, :day, :slot, :room)');
        $exists = $pdo->prepare('SELECT schedule_id FROM doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');

        $count = 0;
        foreach ($rows as $r) {
            if ($mode === 'fill_empty') {
                $exists->execute([':week_id'=>$targetWeekId, ':doctor_id'=>$r['doctor_id'], ':day'=>$r['day_of_week'], ':slot'=>$r['slot_number']]);
                if ($exists->fetch()) continue;
            }
            $ins->execute([
                ':week_id'=>$targetWeekId,
                ':doctor_id'=>$r['doctor_id'],
                ':course_id'=>$r['course_id'],
                ':day'=>$r['day_of_week'],
                ':slot'=>$r['slot_number'],
                ':room'=>$r['room_code'] ?? null,
            ]);
            $count++;
        }

        $pdo->commit();
        echo json_encode(['success'=>true,'data'=>['cloned'=>$count]]);
        exit;
    }

    // Fallback without rooms
    $src = $pdo->prepare('SELECT doctor_id, course_id, day_of_week, slot_number FROM doctor_schedules WHERE week_id = :wid');
    $src->execute([':wid' => $sourceWeekId]);
    $rows = $src->fetchAll();

    $ins = $pdo->prepare('INSERT INTO doctor_schedules (week_id, doctor_id, course_id, day_of_week, slot_number) VALUES (:week_id, :doctor_id, :course_id, :day, :slot)');
    $exists = $pdo->prepare('SELECT schedule_id FROM doctor_schedules WHERE week_id = :week_id AND doctor_id = :doctor_id AND day_of_week = :day AND slot_number = :slot');

    $count = 0;
    foreach ($rows as $r) {
        if ($mode === 'fill_empty') {
            $exists->execute([':week_id'=>$targetWeekId, ':doctor_id'=>$r['doctor_id'], ':day'=>$r['day_of_week'], ':slot'=>$r['slot_number']]);
            if ($exists->fetch()) continue;
        }
        $ins->execute([
            ':week_id'=>$targetWeekId,
            ':doctor_id'=>$r['doctor_id'],
            ':course_id'=>$r['course_id'],
            ':day'=>$r['day_of_week'],
            ':slot'=>$r['slot_number'],
        ]);
        $count++;
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'data'=>['cloned'=>$count]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to clone week.','details'=>$e->getMessage()]);
}
