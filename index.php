<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Admin dashboard by default.
auth_require_page_access('index.php');
auth_require_roles(['admin','management']);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dr.Sheirf</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="course-dashboard">
  <?php render_portal_navbar('index.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Course Dashboard</h1>
      <p class="subtitle">Total hours vs completed hours for each course (based on scheduled slots).</p>
    </header>

    <section class="card">
      <div class="easter-mini" aria-hidden="false">
        <input id="easterEggInput" type="text" maxlength="3" placeholder="700" />
      </div>
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Dashboard</h2>
          <div class="muted" style="margin-top:4px;">Use the filters on the right to narrow results.</div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; justify-content:flex-end;">
            <?php if ((auth_current_user()['role'] ?? '') === 'admin') : ?>
              <a class="btn btn-secondary" href="php/export_database_sql.php">Export Database SQL</a>
              <form class="field" action="php/import_database_sql.php" method="post" enctype="multipart/form-data" style="margin:0;">
                <label class="muted" style="font-size:0.85rem;" for="importSqlFile">Import SQL</label>
                <div style="display:flex; gap:8px; align-items:center;">
                  <input id="importSqlFile" name="sql_file" type="file" accept=".sql" required />
                  <button class="btn btn-secondary" type="submit">Upload</button>
                </div>
              </form>
            <?php endif; ?>
            <div class="field" style="margin:0; min-width:140px;">
              <label class="muted" style="font-size:0.85rem;" for="dashboardYearFilter">Academic Year</label>
              <select id="dashboardYearFilter" class="navlink" style="padding:7px 10px;">
                <option value="">All</option>
                <option value="1">Year 1</option>
                <option value="2">Year 2</option>
                <option value="3">Year 3</option>
              </select>
            </div>
            <div class="field" style="margin:0; min-width:140px;">
              <label class="muted" style="font-size:0.85rem;" for="dashboardSemesterFilter">Semester</label>
              <select id="dashboardSemesterFilter" class="navlink" style="padding:7px 10px;">
                <option value="">All</option>
                <option value="1">Sem 1</option>
                <option value="2">Sem 2</option>
              </select>
            </div>
            <button id="refreshCourseDashboard" class="btn btn-secondary" type="button">Refresh</button>
          </div>
      </div>

      <div id="courseDashboardStatus" class="status" role="status" aria-live="polite"></div>

      <div class="dashboard-grid">
        <div class="dashboard-card">
          <div class="dashboard-card-head">
            <div>
              <div class="dashboard-card-title">Overall Completion</div>
              <div class="dashboard-card-subtitle muted">All filtered courses combined</div>
            </div>

            <div class="dashboard-card-actions" aria-label="Quick links">
              <a class="btn btn-secondary btn-small" href="schedule_builder.php">Open Portal</a>
            </div>
          </div>

          <canvas id="courseDashboardDonut" height="220" aria-label="Overall completion donut" role="img"></canvas>
          <div id="courseDashboardDonutText" class="dashboard-metric"></div>
        </div>

        <div class="dashboard-card dashboard-card-wide">
          <div class="dashboard-card-title">Course Progress (Done vs Remaining)</div>
          <div class="dashboard-card-subtitle muted">Stacked bars per course</div>
          <div class="dashboard-chart-legend" aria-label="Chart legend">
            <span class="legend-item"><span class="legend-swatch legend-done"></span>Done</span>
            <span class="legend-item"><span class="legend-swatch legend-remaining"></span>Remaining</span>
          </div>
          <canvas id="courseDashboardChart" height="420" aria-label="Course progress chart" role="img"></canvas>
        </div>

        <div class="dashboard-card">
          <div class="dashboard-card-head">
            <div>
              <div class="dashboard-card-title">Missionnaire vs Others</div>
              <div class="dashboard-card-subtitle muted">Total course hours (even split across assigned doctors unless manually allocated)</div>
            </div>
          </div>

          <canvas id="missionnairePie" height="240" aria-label="Missionnaire vs other doctors pie chart" role="img"></canvas>
          <div id="missionnairePieText" class="dashboard-metric"></div>
        </div>

        <div class="dashboard-card">
          <div class="dashboard-card-title">Top Remaining</div>
          <div class="dashboard-card-subtitle muted">Courses with most hours left</div>
          <canvas id="courseDashboardTopRemaining" height="240" aria-label="Top remaining courses" role="img"></canvas>
        </div>
      </div>

      <h3 style="margin-top:18px;">Details</h3>
      <div id="courseDashboardList" class="course-progress-list" aria-live="polite"></div>
    </section>

  </main>

  <script src="js/core.js?v=20260205"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/course_dashboard.js?v=20260201"></script>
  <script src="js/schedule_builder.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initCourseDashboardPage?.();
  </script>
</body>
</html>
