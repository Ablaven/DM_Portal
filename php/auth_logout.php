<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';

auth_session_start();
unset($_SESSION['portal_user']);

echo json_encode(['success' => true]);
