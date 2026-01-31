<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_auth_schema.php';

auth_require_roles(['admin'], true);

/**
 * Parse allowed_pages_json into a list of strings.
 * NULL means "use role default" (or full access for admin).
 *
 * @return list<string>|null
 */
function parse_allowed_pages(?string $json): ?array
{
    if ($json === null) {
        return null;
    }

    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $out = [];
    foreach ($decoded as $v) {
        $s = trim((string)$v);
        if ($s === '') continue;
        $out[] = $s;
    }

    return array_values(array_unique($out));
}

try {
    $pdo = get_pdo();
    ensure_auth_schema($pdo);

    $stmt = $pdo->query(
        'SELECT user_id, username, role, doctor_id, student_id, is_active, allowed_pages_json, created_at '
        . 'FROM portal_users '
        . 'ORDER BY user_id DESC'
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $r) {
        $allowed = parse_allowed_pages($r['allowed_pages_json'] ?? null);

        $data[] = [
            'user_id' => (int)($r['user_id'] ?? 0),
            'username' => (string)($r['username'] ?? ''),
            'role' => (string)($r['role'] ?? ''),
            'doctor_id' => $r['doctor_id'] !== null ? (int)$r['doctor_id'] : null,
            'student_id' => $r['student_id'] !== null ? (int)$r['student_id'] : null,
            'is_active' => (int)($r['is_active'] ?? 0) ? 1 : 0,
            'allowed_pages' => $allowed,
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch users.',
        // 'debug' => $e->getMessage(),
    ]);
}
