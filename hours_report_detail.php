<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('hours_report_detail.php');
auth_require_roles(['admin', 'teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$isTeacher = $role === 'teacher';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hours Report</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body>
  <?php render_portal_navbar('hours_report.php'); ?>

  <main class="container container-top course-dashboard">
    <header class="page-header">
      <h1>Hours Report</h1>
      <p class="subtitle">Hours per doctor per subject: allocated vs done vs remaining, plus totals.</p>
    </header>

    <section class="card">
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Details</h2>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; justify-content:flex-end;">
          <div class="field" style="margin:0; min-width:140px;">
            <label class="muted" style="font-size:0.85rem;" for="hoursReportYearFilter">Academic Year</label>
            <select id="hoursReportYearFilter" class="navlink" style="padding:7px 10px;">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field" style="margin:0; min-width:140px;">
            <label class="muted" style="font-size:0.85rem;" for="hoursReportSemesterFilter">Semester</label>
            <select id="hoursReportSemesterFilter" class="navlink" style="padding:7px 10px;">
              <option value="">All</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>
          <button id="hoursReportRefresh" class="btn btn-secondary" type="button">Refresh</button>
        </div>
      </div>

      <div id="hoursReportStatus" class="status" role="status" aria-live="polite"></div>

      <div id="hoursReportRoot" class="course-progress-list" aria-live="polite"></div>
    </section>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/hours_report.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initHoursReportPage?.();
  </script>
</body>
</html>
