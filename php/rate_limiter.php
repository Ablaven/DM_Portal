<?php
declare(strict_types=1);

/**
 * Simple file-based rate limiter for login attempts.
 * Stores attempt counts per IP in the session tmp directory.
 * No database required.
 *
 * Policy:
 *   - 5 failed attempts within 10 minutes → 15-minute lockout
 *   - After lockout expires, counter resets
 */

define('RL_MAX_ATTEMPTS',  5);
define('RL_WINDOW_SECS',   600);   // 10 minutes
define('RL_LOCKOUT_SECS',  900);   // 15 minutes

function rl_storage_path(string $ip): string
{
    $safe = preg_replace('/[^a-f0-9\.\:\-]/i', '_', $ip);
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dmportal_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'ip_' . md5($safe) . '.json';
}

function rl_get_client_ip(): string
{
    // Respect reverse-proxy headers if set, fall back to REMOTE_ADDR
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
        $v = $_SERVER[$h] ?? '';
        if ($v !== '') {
            // X-Forwarded-For may be a comma-separated list; take the first
            return trim(explode(',', $v)[0]);
        }
    }
    return 'unknown';
}

function rl_load(string $path): array
{
    if (!file_exists($path)) {
        return ['attempts' => [], 'locked_until' => 0];
    }
    $data = @json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : ['attempts' => [], 'locked_until' => 0];
}

function rl_save(string $path, array $data): void
{
    @file_put_contents($path, json_encode($data), LOCK_EX);
}

/**
 * Check if the current IP is rate-limited.
 * Returns an array:
 *   ['limited' => bool, 'retry_after' => int (seconds remaining)]
 */
function rl_check_login(): array
{
    $ip   = rl_get_client_ip();
    $path = rl_storage_path($ip);
    $data = rl_load($path);
    $now  = time();

    // Still in lockout window?
    if (($data['locked_until'] ?? 0) > $now) {
        return ['limited' => true, 'retry_after' => $data['locked_until'] - $now];
    }

    // Prune attempts outside the sliding window
    $data['attempts'] = array_values(array_filter(
        $data['attempts'] ?? [],
        fn(int $t) => ($now - $t) < RL_WINDOW_SECS
    ));

    return ['limited' => false, 'retry_after' => 0];
}

/**
 * Record a failed login attempt for the current IP.
 * If the threshold is reached, a lockout is applied.
 */
function rl_record_failure(): void
{
    $ip   = rl_get_client_ip();
    $path = rl_storage_path($ip);
    $data = rl_load($path);
    $now  = time();

    // Prune old attempts
    $data['attempts'] = array_values(array_filter(
        $data['attempts'] ?? [],
        fn(int $t) => ($now - $t) < RL_WINDOW_SECS
    ));

    $data['attempts'][] = $now;

    if (count($data['attempts']) >= RL_MAX_ATTEMPTS) {
        $data['locked_until'] = $now + RL_LOCKOUT_SECS;
        $data['attempts']     = []; // reset so counter is clean after lockout
    }

    rl_save($path, $data);
}

/**
 * Clear rate limit state for the current IP (call on successful login).
 */
function rl_clear(): void
{
    $ip   = rl_get_client_ip();
    $path = rl_storage_path($ip);
    if (file_exists($path)) {
        @unlink($path);
    }
}
