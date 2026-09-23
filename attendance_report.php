<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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

  <main class="container container-top">
    <header class="page-header">
      <h1>Attendance Summary Report</h1>
      <p class="subtitle">Participation rates and attendance statistics per course.</p>
    </header>

    <section class="card">
      <div style="margin-bottom:20px;">
        <h2 style="margin:0 0 6px;">Filters</h2>
        <p class="muted" style="margin:0; font-size:0.9rem;"><?php echo $isTeacher ? 'Showing my courses only.' : 'Filter by academic context.'; ?></p>
      </div>

      <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
        <div class="field" style="margin:0;">
          <label for="attendanceReportsYear" style="font-size:0.85rem; margin-bottom:4px;">Year</label>
          <select id="attendanceReportsYear" class="navlink" style="padding:9px 11px; min-width:120px;">
            <option value="">All</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
          </select>
        </div>

        <div class="field" style="margin:0;">
          <label for="attendanceReportsSemester" style="font-size:0.85rem; margin-bottom:4px;">Semester</label>
          <select id="attendanceReportsSemester" class="navlink" style="padding:9px 11px; min-width:120px;">
            <option value="">All</option>
            <option value="1">Sem 1</option>
            <option value="2">Sem 2</option>
          </select>
        </div>

        <?php if (!$isTeacher) : ?>
        <div class="field" style="margin:0;">
          <label for="attendanceReportsTeacher" style="font-size:0.85rem; margin-bottom:4px;">Professor</label>
          <select id="attendanceReportsTeacher" class="navlink" style="padding:9px 11px; min-width:180px;">
            <option value="">All Professors</option>
          </select>
        </div>
        <?php endif; ?>

        <div class="field" style="margin:0;">
          <label for="attendanceReportsCourse" style="font-size:0.85rem; margin-bottom:4px;">Course</label>
          <select id="attendanceReportsCourse" class="navlink" style="padding:9px 11px; min-width:200px;">
            <option value="">All courses</option>
          </select>
        </div>

        <button id="attendanceReportsRefresh" class="btn btn-secondary" type="button">Refresh</button>
        <button id="exportAttendanceReportXls" class="btn" type="button">Export Excel</button>
      </div>

      <div id="attendanceReportsStatus" class="status" role="status" style="margin-bottom:12px;"></div>

      <!-- Summary stats -->
      <div id="attendanceReportsSummary" style="display:none; margin-bottom:20px; padding:14px 16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:8px;">
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Courses</div>
            <div style="font-size:1.5rem; font-weight:700;" id="attendanceReportsTotalCourses">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Sessions</div>
            <div style="font-size:1.5rem; font-weight:700;" id="attendanceReportsTotalSessions">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Present Records</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--success);" id="attendanceReportsPresent">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Absent Records</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--danger);" id="attendanceReportsAbsent">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Attendance Rate</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="attendanceReportsRate">0%</div>
          </div>
        </div>
      </div>

      <div class="table-wrap" style="max-height:600px; overflow:auto;">
        <table class="data-table" id="attendanceReportsTable">
          <thead>
            <tr>
              <th>Course</th>
              <th>Professor</th>
              <th>Year</th>
              <th>Sem</th>
              <th>Present</th>
              <th>Absent</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody id="attendanceReportsBody"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260228g"></script>
  <script src="js/attendance_report.js?v=20260228g"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAttendanceReportsPage?.();
  </script>
</body>
</html>
