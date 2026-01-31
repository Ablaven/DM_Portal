<?php

declare(strict_types=1);

function dmportal_ensure_easter_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function dmportal_grant_easter_egg(): void
{
    dmportal_ensure_easter_session();
    $_SESSION['easter_egg_ok_at'] = time();
}

function dmportal_has_easter_egg_access(int $ttlSeconds = 300): bool
{
    dmportal_ensure_easter_session();
    $okAt = $_SESSION['easter_egg_ok_at'] ?? null;
    if (!is_int($okAt)) {
        return false;
    }
    return (time() - $okAt) <= $ttlSeconds;
}

function dmportal_require_easter_egg_access(int $ttlSeconds = 300): void
{
    if (!dmportal_has_easter_egg_access($ttlSeconds)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
