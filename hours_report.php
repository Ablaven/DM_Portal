<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('hours_report.php');
auth_require_roles(['admin', 'teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$isTeacher = $role === 'teacher';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $isTeacher ? 'My Reports' : 'The Reports'; ?></title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body>
  <?php render_portal_navbar('hours_report.php'); ?>

  <main class="container container-top reports-page">
    <header class="page-header">
      <h1><?php echo $isTeacher ? 'My Reports' : 'The Reports'; ?></h1>
      <p class="subtitle">Choose a report module to explore performance, evaluations, and attendance.</p>
    </header>

    <section class="reports-grid">
      <a class="report-card" href="hours_report_detail.php">
        <div class="report-card-glow"></div>
        <div class="report-card-content">
          <div class="report-card-icon">⏱️</div>
          <h2>Hours Report</h2>
          <p>Allocated vs done vs remaining hours, grouped by doctor and course.</p>
          <span class="report-card-cta">Open Hours Report</span>
        </div>
      </a>

      <a class="report-card" href="evaluation_reports.php">
        <div class="report-card-glow"></div>
        <div class="report-card-content">
          <div class="report-card-icon">📊</div>
          <h2>Evaluation Reports</h2>
          <p>Insights on grades, evaluation configurations, and progress trends.</p>
          <span class="report-card-cta">Open Evaluation Reports</span>
        </div>
      </a>

      <a class="report-card" href="attendance_report.php">
        <div class="report-card-glow"></div>
        <div class="report-card-content">
          <div class="report-card-icon">✅</div>
          <h2>Attendance Report</h2>
          <p>Attendance snapshots, participation history, and weekly status.</p>
          <span class="report-card-cta">Open Attendance Report</span>
        </div>
      </a>
    </section>

    <?php if ($isTeacher) : ?>
      <section class="teacher-reports-section">
        <div class="teacher-reports-header">
          <div>
            <h2>My Yearly Hours</h2>
            <p class="muted">Semester split highlights for your teaching load.</p>
          </div>
        </div>
        <div id="teacherReportsStatus" class="status" role="status" aria-live="polite"></div>
        <div id="teacherReportsCards" class="teacher-reports-grid"></div>
      </section>
    <?php endif; ?>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/reports_teacher_cards.js?v=20260209"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initTeacherReportCards?.();
  </script>
</body>
</html>
