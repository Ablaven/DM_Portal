<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Lecture Materials page.
auth_require_page_access('lectures.php');

// Teachers, admins, management, and students can access.
auth_require_roles(['teacher', 'admin', 'management', 'student']);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lecture Materials</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/lectures.css?v=20260512b" />
</head>
<body class="students-view">
  <?php render_portal_navbar('lectures.php'); ?>

  <main class="container container-top" id="lecturesRoot">
    <?php if (!in_array($role, ['teacher', 'admin', 'management', 'student'], true)) : ?>
      <p class="status status-error">Access denied.</p>
    <?php endif; ?>
  </main>

  <script src="js/core.js?v=20260425a"></script>
  <script src="js/navbar.js?v=20260425a"></script>
  <script src="js/lectures.js?v=20260512b"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    document.addEventListener('DOMContentLoaded', function () {
      window.dmportal?.initLecturesPage?.();
    });
  </script>
</body>
</html>