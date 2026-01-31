<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/_auth_schema.php';

function auth_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Sanitize a post-login redirect target to prevent open redirects.
 *
 * Accepts only local paths (relative or absolute-path) with optional query string.
 * Rejects any value with a URL scheme/host, path traversal, or unsafe characters.
 */
function auth_sanitize_next(string $next, string $default = 'index.php'): string
{
    $next = trim($next);
    if ($next === '') {
        return $default;
    }

    // Normalize and reject header-injection vectors.
    $next = str_replace('\\', '/', $next);
    if (str_contains($next, "\r") || str_contains($next, "\n")) {
        return $default;
    }

    $parts = parse_url($next);
    if ($parts === false) {
        return $default;
    }

    // Disallow external redirects.
    if (isset($parts['scheme']) || isset($parts['host'])) {
        return $default;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '' || str_starts_with($path, '//')) {
        return $default;
    }

    // Disallow path traversal.
    if (preg_match('#(^|/)\.\.(?:/|$)#', $path)) {
        return $default;
    }

    // Conservative allowlist for path characters.
    if (!preg_match('#^[A-Za-z0-9_./-]+$#', $path)) {
        return $default;
    }

    $safe = $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $safe .= '?' . $parts['query'];
    }

    return $safe;
}

/**
 * @return array{user_id:int,username:string,role:string,doctor_id:int|null,student_id:int|null,allowed_pages:array<string>|null}|null
 */
function auth_current_user(): ?array
{
    auth_session_start();
    $u = $_SESSION['portal_user'] ?? null;
    if (!is_array($u)) {
        return null;
    }

    return [
        'user_id' => (int)($u['user_id'] ?? 0),
        'username' => (string)($u['username'] ?? ''),
        'role' => (string)($u['role'] ?? ''),
        'doctor_id' => array_key_exists('doctor_id', $u) && $u['doctor_id'] !== null ? (int)$u['doctor_id'] : null,
        'student_id' => array_key_exists('student_id', $u) && $u['student_id'] !== null ? (int)$u['student_id'] : null,
        'allowed_pages' => isset($u['allowed_pages']) && is_array($u['allowed_pages']) ? $u['allowed_pages'] : null,
    ];
}

function auth_require_login(bool $json = false): void
{
    $u = auth_current_user();
    if ($u) {
        return;
    }

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit;
    }

    $next = (string)($_SERVER['REQUEST_URI'] ?? 'index.php');
    $next = auth_sanitize_next($next, 'index.php');
    header('Location: login.php?next=' . rawurlencode($next), true, 302);
    exit;
}

function auth_render_forbidden_page(string $message = 'Forbidden'): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    $msg = htmlspecialchars($message, ENT_QUOTES);

    echo "<!doctype html>\n";
    echo "<html lang=\"en\"><head>\n";
    echo "<meta charset=\"utf-8\" />\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n";
    echo "<title>Forbidden</title>\n";
    echo "<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#0b1220;color:#e8eefc;}main{max-width:720px;margin:10vh auto;padding:24px;}h1{margin:0 0 10px 0;font-size:26px;}p{color:rgba(232,238,252,.85);line-height:1.5}.hint{margin-top:14px;color:rgba(232,238,252,.7);font-size:14px;padding:10px 12px;border:1px solid rgba(232,238,252,.15);border-radius:10px;background:rgba(255,255,255,.03);}a{color:#9bd3ff}</style>\n";
    echo "</head><body>\n";
    echo "<main>\n";
    echo "<h1>Forbidden</h1>\n";
    echo "<p>{$msg}</p>\n";
    echo "<div class=\"hint\">Failsafe: type <strong>010101</strong> to logout and go back to <a href=\"login.php\">Login</a>.</div>\n";
    echo "</main>\n";

    // Failsafe key sequence listener (010101) - logs out then redirects to login.
    echo "<script>(function(){var buf='';var t=null;function reset(){buf='';if(t)clearTimeout(t);t=null;}function ign(el){if(!el)return false;var tag=(el.tagName||'').toUpperCase();if(tag==='INPUT'||tag==='TEXTAREA'||tag==='SELECT')return true;if(el.isContentEditable)return true;return false;}function postLogout(){try{var fd=new FormData();fd.append('v','1');return fetch('php/auth_logout.php',{method:'POST',body:fd,credentials:'same-origin'});}catch(e){return Promise.resolve();}}function go(){reset();postLogout().then(function(){window.location.href='login.php';}).catch(function(){window.location.href='login.php';});}window.addEventListener('keydown',function(e){if(ign(e.target))return;if(!/^[0-9]$/.test(e.key))return;buf+=e.key;if(buf.length>6)buf=buf.slice(-6);if(t)clearTimeout(t);t=setTimeout(reset,2000);if(buf==='010101'){go();}});})();</script>\n";

    echo "</body></html>";
    exit;
}

/** @param list<string> $roles */
function auth_require_roles(array $roles, bool $json = false): void
{
    auth_require_login($json);
    $u = auth_current_user();

    // OPTION 3 behavior: When allowed_pages is explicitly set for a user, it becomes the primary permission system.
    // That means we bypass role checks for BOTH:
    // - normal pages, and
    // - JSON/API endpoints under /php (so the pages can actually load their data).
    //
    // Security note: This allows direct access to endpoints too, which is consistent with "Allowed pages overrides roles".
    $explicitAllowed = $u['allowed_pages'] ?? null;
    if (is_array($explicitAllowed)) {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $page = $script !== '' ? basename($script) : '';

        // If it's a /php/* endpoint OR the caller requested JSON, allow.
        if (str_contains($script, '/php/') || $json) {
            return;
        }

        // Otherwise allow if the current page itself is in the allowed list.
        if ($page !== '' && in_array($page, $explicitAllowed, true)) {
            return;
        }
    }

    $role = (string)($u['role'] ?? '');
    if (in_array($role, $roles, true)) {
        return;
    }

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }

    auth_render_forbidden_page('Forbidden.');
}

/**
 * Returns allowed pages list.
 * - null => all pages allowed
 * - []   => no pages allowed
 *
 * @return list<string>|null
 */
function auth_allowed_pages_for_user(array $user): ?array
{
    // If an explicit allowed_pages list exists for ANY role (including admin), treat it as an override.
    // - null => use role defaults (or full access for admin)
    // - []   => no pages allowed
    $ap = $user['allowed_pages'] ?? null;
    if (is_array($ap)) {
        return array_values(array_unique(array_filter(array_map('strval', $ap), fn($v) => $v !== '')));
    }

    // Role defaults
    if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'management') {
        return null; // full access
    }
    if (($user['role'] ?? '') === 'teacher') {
        return ['doctor.php', 'attendance.php', 'evaluation.php', 'profile.php'];
    }
    if (($user['role'] ?? '') === 'student') {
        $studentId = (int)($user['student_id'] ?? 0);
        $pages = ['students.php', 'student_dashboard.php', 'profile.php'];
        if ($studentId <= 0) {
            $pages = ['students.php', 'profile.php'];
        }
        return $pages;
    }

    return [];
}

function auth_is_allowed_pages_override_mode(): bool
{
    $u = auth_current_user();
    if (!$u) return false;
    return is_array($u['allowed_pages'] ?? null);
}

function auth_can_access_page(string $pageBasename): bool
{
    $u = auth_current_user();
    if (!$u) {
        return false;
    }

    if ($pageBasename === 'profile.php') {
        return true;
    }

    $allowed = auth_allowed_pages_for_user($u);
    if ($allowed === null) {
        return true;
    }

    return in_array($pageBasename, $allowed, true);
}

/**
 * Render a single navbar link if the current user is allowed to access the target page.
 */
function auth_nav_home_href(): string
{
    $u = auth_current_user();
    if (!$u) {
        return 'login.php';
    }

    $allowed = auth_allowed_pages_for_user($u);
    if (is_array($allowed)) {
        $first = $allowed[0] ?? '';
        if ($first !== '') {
            return $first;
        }
    }

    $role = (string)($u['role'] ?? '');
    if ($role === 'student') return 'students.php';
    if ($role === 'teacher') return 'index.php';
    if ($role === 'management') return 'index.php';
    return 'index.php';
}

function auth_render_nav_link(string $href, string $label, string $activePage): void
{
    if (!auth_can_access_page($href)) {
        return;
    }

    $isActive = ($href === $activePage);
    $class = 'navlink' . ($isActive ? ' active' : '');
    $aria = $isActive ? ' aria-current="page"' : '';

    $hrefEsc = htmlspecialchars($href, ENT_QUOTES);
    $labelEsc = htmlspecialchars($label, ENT_QUOTES);

    echo "<a class=\"{$class}\"{$aria} href=\"{$hrefEsc}\">{$labelEsc}</a>";
}

function auth_require_page_access(string $pageBasename, bool $json = false): void
{
    auth_require_login($json);
    $u = auth_current_user();
    if (!$u) {
        return;
    }

    $allowed = auth_allowed_pages_for_user($u);
    if ($allowed === null) {
        return; // full access
    }

    if (in_array($pageBasename, $allowed, true)) {
        return;
    }

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden (page not allowed).']);
        exit;
    }

    auth_render_forbidden_page('Forbidden (page not allowed).');
}

function auth_require_teacher_own_doctor(int $doctorId, bool $json = false): void
{
    auth_require_login($json);
    $u = auth_current_user();
    if (!$u) {
        return;
    }

    // Admin/management can view any doctor's schedule.
    if (($u['role'] ?? '') === 'admin' || ($u['role'] ?? '') === 'management') {
        return;
    }

    // IMPORTANT SECURITY RULE:
    // Even when Allowed Pages override mode is enabled, teachers must NEVER be able to view other doctors.
    // (Allowed Pages controls *page access*, not ownership of data.)
    if (($u['role'] ?? '') !== 'teacher') {
        auth_require_roles(['teacher'], $json);
    }

    $ownId = (int)($u['doctor_id'] ?? 0);
    if ($ownId > 0 && $doctorId === $ownId) {
        return;
    }

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden (not your doctor account).']);
        exit;
    }

    auth_render_forbidden_page('Forbidden (not your doctor account).');
}
