<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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

  <main class="container container-top">
    <header class="page-header">
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
            <a href="hours_report.php" class="btn btn-small btn-secondary" style="text-decoration:none; padding:6px 12px;">← Reports</a>
          </div>
          <h1>Evaluation Reports</h1>
          <p class="subtitle">Student performance, grades, and assessment analytics.</p>
        </div>
      </div>
    </header>

    <section class="card">
      <div style="margin-bottom:20px;">
        <h2 style="margin:0 0 6px;">Filters</h2>
        <p class="muted" style="margin:0; font-size:0.9rem;"><?php echo $isTeacher ? 'Showing my courses only.' : 'Filter by academic context.'; ?></p>
      </div>

      <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
        <div class="field" style="margin:0;">
          <label for="evaluationReportsYear" style="font-size:0.85rem; margin-bottom:4px;">Year</label>
          <select id="evaluationReportsYear" class="navlink" style="padding:9px 11px; min-width:120px;">
            <option value="">All</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
          </select>
        </div>

        <div class="field" style="margin:0;">
          <label for="evaluationReportsSemester" style="font-size:0.85rem; margin-bottom:4px;">Semester</label>
          <select id="evaluationReportsSemester" class="navlink" style="padding:9px 11px; min-width:120px;">
            <option value="">All</option>
            <option value="1">Sem 1</option>
            <option value="2">Sem 2</option>
          </select>
        </div>

        <?php if (!$isTeacher) : ?>
        <div class="field" style="margin:0;">
          <label for="evaluationReportsTeacher" style="font-size:0.85rem; margin-bottom:4px;">Professor</label>
          <select id="evaluationReportsTeacher" class="navlink" style="padding:9px 11px; min-width:180px;">
            <option value="">All Professors</option>
          </select>
        </div>
        <?php endif; ?>

        <div class="field" style="margin:0;">
          <label for="evaluationReportsCourse" style="font-size:0.85rem; margin-bottom:4px;">Course</label>
          <select id="evaluationReportsCourse" class="navlink" style="padding:9px 11px; min-width:200px;">
            <option value="">All courses</option>
          </select>
        </div>

        <button id="evaluationReportsRefresh" class="btn btn-secondary" type="button">Refresh</button>
        
        <!-- Export dropdown -->
        <div style="position:relative;">
          <button id="evaluationReportsExportBtn" class="btn" type="button" style="display:flex; align-items:center; gap:6px;">
            Export <span style="font-size:0.7rem;">▼</span>
          </button>
          <div id="evaluationReportsExportMenu" style="display:none; position:absolute; top:calc(100% + 4px); right:0; min-width:260px; background:var(--dropdown-bg); border:1px solid var(--card-border); border-radius:12px; padding:8px; box-shadow:var(--shadow); z-index:1000; backdrop-filter:blur(48px);">
            <button id="exportEvaluationReportSummary" type="button" style="width:100%; text-align:left; padding:10px 12px; border:none; background:transparent; color:var(--text); cursor:pointer; border-radius:8px; font-size:0.9rem; font-weight:600; transition: background 150ms;">
              📊 Final Grades (Filtered)
            </button>
            <?php if (!$isTeacher) : ?>
            <button id="exportEvaluationReportSummaryAll" type="button" style="width:100%; text-align:left; padding:10px 12px; border:none; background:transparent; color:var(--text); cursor:pointer; border-radius:8px; font-size:0.9rem; font-weight:600; transition: background 150ms;">
              📋 Final Grades (All Subjects)
            </button>
            <?php endif; ?>
            <button id="exportEvaluationReportGrades" type="button" style="width:100%; text-align:left; padding:10px 12px; border:none; background:transparent; color:var(--text); cursor:pointer; border-radius:8px; font-size:0.9rem; font-weight:600; transition: background 150ms;">
              📈 Detailed Grades
            </button>
          </div>
        </div>
      </div>

      <div id="evaluationReportsStatus" class="status" role="status" style="margin-bottom:12px;"></div>

      <!-- Summary stats -->
      <div id="evaluationReportsSummary" style="display:none; margin-bottom:20px; padding:14px 16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:8px;">
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Courses</div>
            <div style="font-size:1.5rem; font-weight:700;" id="evaluationReportsTotalCourses">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Graded Students</div>
            <div style="font-size:1.5rem; font-weight:700;" id="evaluationReportsGradedStudents">0</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Avg Final Grade</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="evaluationReportsAvgFinal">—</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Avg Attendance</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--success);" id="evaluationReportsAvgAttendance">—</div>
          </div>
        </div>
      </div>

      <div class="table-wrap" style="max-height:600px; overflow:auto;">
        <table class="data-table" id="evaluationReportsTable">
          <thead>
            <tr>
              <th>Course</th>
              <th>Professor</th>
              <th>Year</th>
              <th>Sem</th>
              <th>Avg Final</th>
              <th>Avg Attendance</th>
              <th>Graded</th>
            </tr>
          </thead>
          <tbody id="evaluationReportsBody"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260228g"></script>
  <script src="js/evaluation_reports.js?v=20260228g"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initEvaluationReportsPage?.();
  </script>
</body>
</html>
