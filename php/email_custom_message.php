<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_smtp_mailer.php';

header('Content-Type: application/json');

auth_require_roles(['admin', 'management'], true);

$input = json_decode(file_get_contents('php://input') ?: '{}', true);

$category = strtolower(trim((string)($input['category'] ?? '')));
$target = strtolower(trim((string)($input['target'] ?? '')));
$recipientId = (int)($input['recipient_id'] ?? 0);
$yearLevel = (int)($input['year_level'] ?? 0);
$subject = trim((string)($input['subject'] ?? ''));
$body = (string)($input['body'] ?? '');

if ($category !== 'teacher' && $category !== 'student') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid category. Expected teacher or student.']);
    exit;
}

if ($target !== 'all' && $target !== 'single') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid target. Expected all or single.']);
    exit;
}

if ($target === 'single' && $recipientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'recipient_id is required for single target.']);
    exit;
}

if ($subject === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Subject is required.']);
    exit;
}

if (trim($body) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message body is required.']);
    exit;
}

try {
    $pdo = get_pdo();

    $emails = [];

    if ($category === 'teacher') {
        if ($target === 'single') {
            $stmt = $pdo->prepare(
                "SELECT email
                 FROM doctors
                 WHERE doctor_id = :id
                   AND email IS NOT NULL AND TRIM(email) <> ''
                 LIMIT 1"
            );
            $stmt->execute([':id' => $recipientId]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Selected doctor not found or has no email.']);
                exit;
            }
            $emails[] = trim((string)$row['email']);
        } else {
            $stmt = $pdo->query(
                "SELECT DISTINCT TRIM(email) AS email
                 FROM doctors
                 WHERE email IS NOT NULL AND TRIM(email) <> ''"
            );
            foreach ($stmt->fetchAll() as $row) {
                $email = trim((string)($row['email'] ?? ''));
                if ($email !== '' && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
            }
        }
    } else {
        if ($target === 'single') {
            $stmt = $pdo->prepare(
                "SELECT email
                 FROM students
                 WHERE student_id = :id
                   AND email IS NOT NULL AND TRIM(email) <> ''
                 LIMIT 1"
            );
            $stmt->execute([':id' => $recipientId]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Selected student not found or has no email.']);
                exit;
            }
            $emails[] = trim((string)$row['email']);
        } else {
            if ($yearLevel > 0) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT TRIM(email) AS email
                     FROM students
                     WHERE email IS NOT NULL AND TRIM(email) <> ''
                       AND year_level = :year_level"
                );
                $stmt->execute([':year_level' => $yearLevel]);
            } else {
                $stmt = $pdo->query(
                    "SELECT DISTINCT TRIM(email) AS email
                     FROM students
                     WHERE email IS NOT NULL AND TRIM(email) <> ''"
                );
            }

            foreach ($stmt->fetchAll() as $row) {
                $email = trim((string)($row['email'] ?? ''));
                if ($email !== '' && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
            }
        }
    }

    if (empty($emails)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No recipient emails found for this selection.']);
        exit;
    }

    $recipient = array_shift($emails);
    $cc = [];

    if ($target === 'all' && !empty($emails)) {
        $cc = array_values(array_unique($emails));
    }

    $mailer = new DmportalSmtpMailer();
    $mailer->send($recipient, $subject, $body, [], $cc);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

