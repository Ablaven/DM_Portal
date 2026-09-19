<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Student dashboard page.
auth_require_page_access('student_dashboard.php');
auth_require_roles(['student']);

$user = auth_current_user();
$studentId = (int)($user['student_id'] ?? 0);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Dashboard</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="student-dashboard">
  <?php render_portal_navbar('student_dashboard.php'); ?>

  <main class="container container-top" role="main">
    <header class="page-header">
      <h1>Student Dashboard</h1>
      <p class="subtitle">Your academic performance at a glance</p>
    </header>

    <div id="dashboardStatus" class="status" role="status" aria-live="polite"></div>

    <section class="card" aria-label="Academic Performance Overview">
      <div id="dashboardContainer">
        <div class="dashboard-grid">
          <!-- Grades Card -->
          <section class="dashboard-card dashboard-card-wide" id="gradesCard" aria-labelledby="gradesCardTitle">
            <h2 id="gradesCardTitle" class="dashboard-card-title">              <span>My Grades</span>
            </h2>
            <p class="dashboard-card-subtitle muted">Your performance across all courses</p>
            <div id="gradesContent" class="dashboard-card-content" role="region" aria-live="polite">
              <div class="loading-spinner" role="status" aria-label="Loading grades">Loading...</div>
            </div>
          </section>

          <!-- Attendance Card -->
          <section class="dashboard-card" id="attendanceCard" aria-labelledby="attendanceCardTitle">
            <h2 id="attendanceCardTitle" class="dashboard-card-title">              <span>Attendance</span>
            </h2>
            <p class="dashboard-card-subtitle muted">Your class participation</p>
            <div id="attendanceContent" class="dashboard-card-content" role="region" aria-live="polite">
              <div class="loading-spinner" role="status" aria-label="Loading attendance">Loading...</div>
            </div>
          </section>

          <!-- Performance Card -->
          <section class="dashboard-card" id="performanceCard" aria-labelledby="performanceCardTitle">
            <h2 id="performanceCardTitle" class="dashboard-card-title">              <span>Performance Metrics</span>
            </h2>
            <p class="dashboard-card-subtitle muted">Overview of your academic standing</p>
            <div id="performanceContent" class="dashboard-card-content" role="region" aria-live="polite">
              <div class="loading-spinner" role="status" aria-label="Loading performance">Loading...</div>
            </div>
          </section>
        </div>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260914a"></script>
  <script src="js/navbar.js?v=20260914a"></script>
  <script src="js/student_dashboard.js?v=20260914a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initStudentDashboard?.();
  </script>
</body>
</html>

