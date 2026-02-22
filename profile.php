<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('profile.php');
auth_require_login();

$u = auth_current_user();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profile</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php
    require_once __DIR__ . '/php/_navbar.php';
    render_portal_navbar('profile.php');
  ?>

  <main class="container container-top container-narrow">
    <header class="page-header">
      <h1>Profile</h1>
      <p class="subtitle">Manage your account settings.</p>
    </header>

    <section class="card">
      <div class="card-header">
        <div>
          <h2 style="margin:0;">Change Password</h2>
          <div class="muted" style="margin-top:4px;">Signed in as <strong><?php echo htmlspecialchars($u['username'] ?? '', ENT_QUOTES); ?></strong> (<?php echo htmlspecialchars($u['role'] ?? '', ENT_QUOTES); ?>)</div>
        </div>
      </div>

      <form id="changePasswordForm" class="form" autocomplete="off">
        <div class="field">
          <label for="current_password">Current Password</label>
          <input id="current_password" name="current_password" type="password" required autocomplete="current-password" />
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" required autocomplete="new-password" />
          </div>
          <div class="field">
            <label for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password" />
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Update Password</button>
        </div>
        <div id="changePasswordStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/profile.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initProfilePage?.();
  </script>
</body>
</html>
