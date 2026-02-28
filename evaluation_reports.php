<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('evaluation_reports.php');
auth_require_roles(['admin', 'teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$isTeacher = $role === 'teacher';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Evaluation Reports</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('evaluation_reports.php'); ?>

  <main class="container container-top report-detail-page">
    <header class="page-header">
      <h1>Evaluation Reports</h1>
      <p class="subtitle">Read-only evaluation summaries with course averages and performance signals.</p>
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
          <label for="evaluationReportsYear">Year</label>
          <select id="evaluationReportsYear">
            <option value="">All</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
          </select>
        </div>
        <div class="field">
          <label for="evaluationReportsSemester">Semester</label>
          <select id="evaluationReportsSemester">
            <option value="">All</option>
            <option value="1">Sem 1</option>
            <option value="2">Sem 2</option>
          </select>
        </div>
        <div class="field">
          <label for="evaluationReportsCourse">Course</label>
          <select id="evaluationReportsCourse">
            <option value="">All courses</option>
          </select>
        </div>
        <div class="page-actions">
          <button id="evaluationReportsRefresh" class="btn btn-secondary" type="button">Refresh</button>
          <button id="exportEvaluationReportSummary" class="btn btn-secondary" type="button">Export Final Grades</button>
          <?php if (!$isTeacher) { ?>
            <button id="exportEvaluationReportSummaryAll" class="btn btn-secondary" type="button">Export Final Grades (All Subjects)</button>
          <?php } ?>
          <button id="exportEvaluationReportGrades" class="btn btn-secondary" type="button">Export Detailed Grades</button>
        </div>
      </div>

      <div id="evaluationReportsStatus" class="status" role="status" aria-live="polite"></div>

      <div id="evaluationReportsMetrics" class="report-metrics"></div>

      <div class="report-table-wrap">
        <table class="report-table" aria-label="Evaluation report summary">
          <thead>
            <tr>
              <th>Course</th>
              <th>Doctors</th>
              <th class="col-number">Year</th>
              <th class="col-number">Sem</th>
              <th class="col-number">Avg Final</th>
              <th class="col-number">Avg Attendance</th>
              <th class="col-number">Graded</th>
            </tr>
          </thead>
          <tbody id="evaluationReportsBody"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/evaluation_reports.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initEvaluationReportsPage?.();
  </script>
</body>
</html>
