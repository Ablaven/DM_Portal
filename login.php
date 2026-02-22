<?php

declare(strict_types=1);

require_once __DIR__ . '/php/db_connect.php';
require_once __DIR__ . '/php/_auth_schema.php';
require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// If already logged in, go to best landing.
$u = auth_current_user();
if ($u) {
    // If Allowed Pages is explicitly set for this user, redirect to the first allowed page.
    // Otherwise fall back to role defaults.
    header('Location: ' . auth_nav_home_href(), true, 302);
    exit;
}

$pdo = get_pdo();
ensure_auth_schema($pdo);
$usersCount = count_portal_users($pdo);

$next = auth_sanitize_next((string)($_GET['next'] ?? ''), 'index.php');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="course-dashboard">
  <?php render_portal_brand_header('index.php'); ?>

  <main>
    <?php if ($usersCount === 0): ?>
    <div class="auth-wrap" style="flex-direction:column; gap:16px;">
      <div class="auth-card">
        <h1>Create First Admin</h1>
        <p class="subtitle">This appears only once when there are no users.</p>

        <form id="firstAdminForm" class="form" autocomplete="off">
          <div class="grid-2">
            <div class="field">
              <label for="first_full_name">Full Name</label>
              <input id="first_full_name" name="full_name" type="text" placeholder="Admin" />
            </div>
            <div class="field">
              <label for="first_email">Email</label>
              <input id="first_email" name="email" type="email" placeholder="admin@example.com" />
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label for="first_username">Username</label>
              <input id="first_username" name="username" type="text" required />
            </div>
            <div class="field">
              <label for="first_password">Password</label>
              <input id="first_password" name="password" type="password" required autocomplete="new-password" />
            </div>
          </div>

          <div class="actions">
            <button class="btn" type="submit">Create Admin</button>
          </div>

          <div id="firstAdminStatus" class="status" role="status" aria-live="polite"></div>
        </form>
      </div>

      <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="subtitle">Sign in to the Digital Marketing Portal.</p>

        <form id="loginForm" class="form" autocomplete="off">
          <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required />
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" />
          </div>

          <input type="hidden" id="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES); ?>" />

          <div class="actions">
            <button class="btn" type="submit">Login</button>
          </div>

          <div id="loginStatus" class="status" role="status" aria-live="polite"></div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="subtitle">Sign in to the Digital Marketing Portal.</p>

        <form id="loginForm" class="form" autocomplete="off">
          <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required />
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" />
          </div>

          <input type="hidden" id="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES); ?>" />

          <div class="actions">
            <button class="btn" type="submit">Login</button>
          </div>

          <div id="loginStatus" class="status" role="status" aria-live="polite"></div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </main>

  <script>
    (function() {
      const form = document.getElementById('loginForm');
      const status = document.getElementById('loginStatus');
      const next = document.getElementById('next').value || 'index.php';
      const hasExplicitNext = (function(){
        try {
          const q = new URLSearchParams(window.location.search || '');
          return q.has('next') && String(q.get('next') || '').trim() !== '';
        } catch {
          return false;
        }
      })();

      function setStatus(msg, ok) {
        status.textContent = msg || '';
        status.style.color = ok ? '#b4ffcc' : '#ffb4b4';
      }

      const firstAdminForm = document.getElementById('firstAdminForm');
      const firstAdminStatus = document.getElementById('firstAdminStatus');

      function setFirstStatus(msg, ok) {
        if (!firstAdminStatus) return;
        firstAdminStatus.textContent = msg || '';
        firstAdminStatus.style.color = ok ? '#b4ffcc' : '#ffb4b4';
      }

      firstAdminForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setFirstStatus('Creating…', true);
        try {
          const fd = new FormData(firstAdminForm);
          const resp = await fetch('php/auth_create_first_admin.php', { method: 'POST', body: fd });
          const payload = await resp.json().catch(() => null);
          if (!resp.ok || !payload || !payload.success) {
            setFirstStatus((payload && payload.error) ? payload.error : 'Failed to create admin.', false);
            return;
          }
          setFirstStatus('Admin created. You can now login.', true);
          firstAdminForm.reset();
        } catch (err) {
          setFirstStatus('Failed to create admin.', false);
        }
      });

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        setStatus('Logging in…');
        const fd = new FormData(form);

        try {
          const resp = await fetch('php/auth_login.php', { method: 'POST', body: fd });
          const payload = await resp.json().catch(() => null);
          if (!resp.ok || !payload || !payload.success) {
            setStatus((payload && payload.error) ? payload.error : 'Login failed.');
            return;
          }

          const role = payload?.data?.role;
          const landing = String(payload?.data?.landing || '').trim();

          // If the user was sent to login because they tried to visit a specific page,
          // honor the sanitized ?next=... value.
          if (hasExplicitNext) {
            window.location.href = next;
            return;
          }

          // Otherwise, use the server-computed landing page (first allowed page if override list exists).
          if (landing) {
            window.location.href = landing;
            return;
          }

          // Fallbacks (should rarely be needed)
          if (role === 'student') {
            window.location.href = 'students.php';
            return;
          }
          if (role === 'teacher') {
            window.location.href = 'index.php';
            return;
          }
          window.location.href = 'index.php';
        } catch (err) {
          setStatus('Login failed.');
        }
      });
    })();
  </script>
</body>
</html>
