<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('hours_report.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
          <h1><?php echo $isTeacher ? 'My Reports' : 'Reports Hub'; ?></h1>
          <p class="subtitle">Access comprehensive analytics, performance insights, and detailed breakdowns.</p>
        </div>
        <?php if (!$isTeacher) : ?>
        <a href="admin_panel.php" class="btn btn-small btn-secondary" style="text-decoration:none; padding:8px 16px;">
          ⚙️ Admin Panel
        </a>
        <?php endif; ?>
      </div>
    </header>

    <!-- Quick Stats Overview -->
    <?php if (!$isTeacher) : ?>
    <section class="card" style="margin-bottom:20px;">
      <div style="margin-bottom:16px;">
        <h2 style="margin:0 0 6px;">Quick Overview</h2>
        <p class="muted" style="margin:0; font-size:0.9rem;">Real-time statistics across all reports.</p>
      </div>
      <div id="reportsHubStats" style="display:flex; gap:20px; flex-wrap:wrap; padding:14px 0;">
        <div style="flex:1; min-width:150px;">
          <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">📊 Active Courses</div>
          <div style="font-size:1.8rem; font-weight:700;" id="statsActiveCourses">-</div>
        </div>
        <div style="flex:1; min-width:150px;">
          <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">👥 Total Students</div>
          <div style="font-size:1.8rem; font-weight:700;" id="statsTotalStudents">-</div>
        </div>
        <div style="flex:1; min-width:150px;">
          <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">👨‍🏫 Professors</div>
          <div style="font-size:1.8rem; font-weight:700;" id="statsTotalDoctors">-</div>
        </div>
        <div style="flex:1; min-width:150px;">
          <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">⏱️ Total Hours</div>
          <div style="font-size:1.8rem; font-weight:700; color:var(--accent);" id="statsTotalHours">-</div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Report Cards Grid -->
    <section style="margin-bottom:30px;">
      <div style="margin-bottom:16px;">
        <h2 style="margin:0 0 6px;">Available Reports</h2>
        <p class="muted" style="margin:0; font-size:0.9rem;">Click any report to explore detailed data and export options.</p>
      </div>
      
      <div class="reports-grid">
        <a class="report-card" href="hours_report_detail.php">
          <div class="report-card-glow"></div>
          <div class="report-card-content">
            <div class="report-card-icon">⏱️</div>
            <h2>Hours Report</h2>
            <p>Teaching hours breakdown by professor and course.</p>
            <span class="report-card-cta">View Report →</span>
          </div>
        </a>

        <a class="report-card" href="evaluation_reports.php">
          <div class="report-card-glow"></div>
          <div class="report-card-content">
            <div class="report-card-icon">📊</div>
            <h2>Evaluation Reports</h2>
            <p>Student grades and assessment performance.</p>
            <span class="report-card-cta">View Report →</span>
          </div>
        </a>

        <a class="report-card" href="attendance_report.php">
          <div class="report-card-glow"></div>
          <div class="report-card-content">
            <div class="report-card-icon">✅</div>
            <h2>Attendance Summary</h2>
            <p>Participation rates and session breakdowns.</p>
            <span class="report-card-cta">View Report →</span>
          </div>
        </a>
      </div>
    </section>

    <?php if ($isTeacher) : ?>
      <!-- Teacher-specific section -->
      <section class="card">
        <div style="margin-bottom:20px;">
          <h2 style="margin:0 0 6px;">My Teaching Summary</h2>
          <p class="muted" style="margin:0; font-size:0.9rem;">Quick overview of your current semester teaching load and progress.</p>
        </div>
        <div id="teacherReportsStatus" class="status" role="status" aria-live="polite"></div>
        <div id="teacherReportsCards" class="teacher-reports-grid"></div>
      </section>
    <?php endif; ?>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260228g"></script>
  <script src="js/reports_teacher_cards.js?v=20260228g"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initTeacherReportCards?.();

    // Load quick stats for admin
    <?php if (!$isTeacher) : ?>
    (async function loadQuickStats() {
      try {
        const { fetchJson } = window.dmportal || {};
        if (!fetchJson) return;

        // Fetch courses count
        const coursesRes = await fetchJson('php/get_courses.php');
        if (coursesRes?.success && coursesRes?.data) {
          document.getElementById('statsActiveCourses').textContent = coursesRes.data.length || 0;
        }

        // Fetch students count
        const studentsRes = await fetchJson('php/get_students.php');
        if (studentsRes?.success && studentsRes?.data) {
          document.getElementById('statsTotalStudents').textContent = studentsRes.data.length || 0;
        }

        // Fetch doctors count
        const doctorsRes = await fetchJson('php/get_doctors.php');
        if (doctorsRes?.success && doctorsRes?.data) {
          document.getElementById('statsTotalDoctors').textContent = doctorsRes.data.length || 0;
        }

        // Fetch total hours
        const hoursRes = await fetchJson('php/get_courses.php');
        if (hoursRes?.success && hoursRes?.data) {
          const totalHours = hoursRes.data.reduce((sum, course) => {
            return sum + (Number(course.total_hours) || 0);
          }, 0);
          document.getElementById('statsTotalHours').textContent = totalHours.toLocaleString() + 'h';
        }
      } catch (err) {
        console.error('Failed to load quick stats:', err);
      }
    })();
    <?php endif; ?>
  </script>
</body>
</html>
