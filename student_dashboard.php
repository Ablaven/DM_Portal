<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('student_dashboard.php');
auth_require_login();

auth_require_roles(['admin','management','student']);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="students-view">
  <?php render_portal_navbar('student_dashboard.php'); ?>

  <main class="container container-top grades-page">
    <header class="page-header">
      <h1 id="studentGradesTitle">Student Dashboard</h1>
      <p id="studentGradesSubtitle" class="subtitle">Evaluation insights per student.</p>
    </header>

    <section class="card" id="studentDashboardVisuals" style="display:none;">
      <div class="dashboard-card-head" style="margin-bottom:12px;">
        <div>
          <div class="dashboard-card-title">My Progress Insights</div>
          <div class="dashboard-card-subtitle">Actionable highlights based on your latest results.</div>
        </div>
      </div>
      <div class="dashboard-grid" id="studentDashboardInsights">
        <div class="dashboard-card">
          <div class="dashboard-card-title">Overall Average</div>
          <div class="dashboard-card-subtitle">Final grades across courses</div>
          <div class="student-insight-row">
            <div class="dashboard-metric student-insight-value" id="studentInsightAverage">--</div>
            <span class="student-dashboard-badge" id="studentInsightAverageBadge">--</span>
          </div>
          <div class="student-insight-note" id="studentInsightAverageNote">--</div>
        </div>
        <div class="dashboard-card">
          <div class="dashboard-card-title">Strongest Course</div>
          <div class="dashboard-card-subtitle">Your highest final grade</div>
          <div class="student-insight-row">
            <div class="dashboard-metric student-insight-value" id="studentInsightTopCourse">--</div>
            <span class="student-dashboard-badge" id="studentInsightTopBadge">--</span>
          </div>
          <div class="student-insight-note" id="studentInsightTopScore">--</div>
        </div>
        <div class="dashboard-card">
          <div class="dashboard-card-title">Needs Attention</div>
          <div class="dashboard-card-subtitle">Lowest final grade</div>
          <div class="student-insight-row">
            <div class="dashboard-metric student-insight-value" id="studentInsightLowCourse">--</div>
            <span class="student-dashboard-badge" id="studentInsightLowBadge">--</span>
          </div>
          <div class="student-insight-note" id="studentInsightLowScore">--</div>
        </div>
        <div class="dashboard-card">
          <div class="dashboard-card-title">Attendance Risk</div>
          <div class="dashboard-card-subtitle">Courses below 12/20</div>
          <div class="student-insight-row">
            <div class="dashboard-metric student-insight-value" id="studentInsightAttendanceRisk">--</div>
            <span class="student-dashboard-badge" id="studentInsightAttendanceBadge">--</span>
          </div>
          <div class="student-insight-note" id="studentInsightAttendanceNote">--</div>
        </div>
      </div>
      <div class="dashboard-grid" id="studentDashboardTrends">
        <div class="dashboard-card dashboard-card-wide">
          <div class="dashboard-card-title">Trend Signals</div>
          <div class="dashboard-card-subtitle">How your courses compare to your overall average</div>
          <div class="student-trend-list" id="studentTrendList"></div>
        </div>
      </div>
    </section>

    <section class="card" id="studentDashboardTable">
      <div class="schedule-header" style="align-items:flex-end;">
        <div class="controls" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
          <div class="field" style="min-width:180px;">
            <label for="studentGradesYear">Year</label>
            <select id="studentGradesYear">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field" style="min-width:160px;">
            <label for="studentGradesSemester">Semester</label>
            <select id="studentGradesSemester">
              <option value="">All</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>
          <div class="field" id="studentGradesAdminSelect" style="min-width:280px; display:none;">
            <label for="studentGradesStudentSelect">Student</label>
            <select id="studentGradesStudentSelect">
              <option value="">All students</option>
            </select>
          </div>
          <div class="field" id="studentGradesCourseSelectWrap" style="min-width:280px; display:none;">
            <label for="studentGradesCourseSelect">Course</label>
            <select id="studentGradesCourseSelect">
              <option value="">All courses</option>
            </select>
          </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
          <button id="studentGradesRefresh" class="btn btn-secondary btn-small" type="button">Refresh</button>
          <div id="studentGradesStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <div class="schedule-wrap" style="margin-top:12px;">
        <div id="studentGradesCards" class="dashboard-grid" style="display:none;"></div>
        <table class="schedule-grid" aria-label="Student dashboard list">
          <thead>
            <tr>
              <th style="width:240px;" data-col="student">Student</th>
              <th style="width:280px;" data-col="course">Course</th>
              <th style="width:120px;" data-col="year">Year</th>
              <th style="width:120px;" data-col="semester">Sem</th>
              <th style="width:160px;" data-col="attendance">Attendance</th>
              <th style="width:160px;" data-col="final">Final Grade</th>
            </tr>
          </thead>
          <tbody id="studentGradesBody"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/student_dashboard.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initStudentDashboardPage?.();
  </script>
</body>
</html>
