<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('attendance_report.php');
auth_require_roles(['admin', 'teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$isTeacher = $role === 'teacher';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Report</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('attendance_report.php'); ?>

  <main class="container container-top report-detail-page">
    <header class="page-header">
      <h1>Attendance Report</h1>
      <p class="subtitle">Read-only attendance summaries per course with participation rates.</p>
    </header>

    <section class="card report-detail-card">
      <div class="card-header">
        <div>
          <h2>Overview</h2>
          <p class="muted"><?php echo $isTeacher ? 'Scoped to my courses.' : 'All courses and years.'; ?></p>
        </div>
      </div>

      <div class="report-filters report-filters-grid filter-bar" style="margin-top:12px;">
        <div class="field">
          <label for="attendanceReportsYear">Year</label>
          <select id="attendanceReportsYear">
            <option value="">All</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
          </select>
        </div>
        <div class="field">
          <label for="attendanceReportsCourse">Course</label>
          <select id="attendanceReportsCourse">
            <option value="">All courses</option>
          </select>
        </div>
        <div class="page-actions">
          <button id="attendanceReportsRefresh" class="btn btn-secondary" type="button">Refresh</button>
          <button id="exportAttendanceReportXls" class="btn btn-secondary" type="button">Export Excel</button>
        </div>
      </div>

      <div id="attendanceReportsStatus" class="status" role="status" aria-live="polite"></div>

      <div id="attendanceReportsMetrics" class="report-metrics"></div>

      <div class="report-table-wrap">
        <table class="report-table" aria-label="Attendance report summary">
          <thead>
            <tr>
              <th>Course</th>
              <th>Doctor</th>
              <th class="col-number">Year</th>
              <th class="col-number">Present</th>
              <th class="col-number">Absent</th>
              <th class="col-number">Attendance Rate</th>
            </tr>
          </thead>
          <tbody id="attendanceReportsBody"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/attendance_report.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAttendanceReportsPage?.();
  </script>
</body>
</html>
