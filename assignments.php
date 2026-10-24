<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Assignments page - accessible to teachers, students, admin, and management
auth_require_page_access('assignments.php');
auth_require_roles(['teacher', 'admin', 'management', 'student']);

$u    = auth_current_user();
$role = (string)($u['role'] ?? '');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Assignments</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/assignments.css?v=20261021a" />
</head>
<body class="assignments-view">
  <?php render_portal_navbar('assignments.php'); ?>

  <main class="container container-top" id="assignmentsRoot">
    <?php if (!in_array($role, ['teacher', 'admin', 'management', 'student'], true)) : ?>
      <p class="status status-error">Access denied.</p>
    <?php endif; ?>
  </main>

  <script src="js/core.js?v=20260425a"></script>
  <script src="js/navbar.js?v=20260425a"></script>
  <script src="js/assignments.js?v=20261021a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    document.addEventListener('DOMContentLoaded', function () {
      window.dmportal?.initAssignmentsPage?.();
    });
  </script>
</body>
</html>
