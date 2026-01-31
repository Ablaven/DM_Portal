<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Doctor read-only page. Always reflects current DB schedule.
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

auth_require_page_access('doctor.php');

// Teachers can only open their own doctor_id.
// If no doctor_id is provided, default to the logged-in teacher's doctor_id.
$u = auth_current_user();
if (($u['role'] ?? '') === 'teacher' && $doctorId <= 0) {
    $doctorId = (int)($u['doctor_id'] ?? 0);
}

if ($doctorId > 0) {
    auth_require_teacher_own_doctor($doctorId);
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="doctor-view">
  <?php render_portal_navbar('doctor.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1 id="doctorName">Doctor Schedule</h1>
      <p class="subtitle">Read-only view (Sun–Thu)</p>
    </header>

    <section class="card">
      <div class="schedule-header">
        <div class="muted">Week starts Sunday • Each slot = 1 hour 30 minutes</div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
          <select id="doctorYearFilter" class="navlink" style="padding:7px 10px;">
            <option value="">All Years</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
          </select>
          <select id="doctorSemesterFilter" class="navlink" style="padding:7px 10px;">
            <option value="">All Sem</option>
            <option value="1">Sem 1</option>
            <option value="2">Sem 2</option>
          </select>
          <select id="doctorWeekSelect" class="navlink" style="padding:7px 10px;">
            <option value="">Loading…</option>
          </select>
          <button id="exportDoctorXls" class="btn btn-secondary btn-small" type="button">Export Excel (.xlsx)</button>
          <a id="doctorEmail" class="icon-btn" href="" target="_blank" rel="noopener" title="Email" aria-label="Email" aria-disabled="true">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></svg>
          </a>
          <a id="doctorWhatsApp" class="icon-btn" href="" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp" aria-disabled="true">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20.5 3.5A11 11 0 0 0 2.9 17.8L2 22l4.3-.9A11 11 0 0 0 20.5 3.5Zm-8.9 17a9 9 0 0 1-4.6-1.2l-.3-.2-2.6.6.6-2.5-.2-.3A9 9 0 1 1 11.6 20.5Zm5-6.4c-.3-.2-1.6-.8-1.9-.9s-.5-.1-.7.2-.8.9-1.1.4-.4.3-.2.1a7.4 7.4 0 0 1-2.2-1.4 8.2 8.2 0 0 1-1.5-1.9c-.2-.4 0-.6.2-.8l.4-.5c.1-.2.2-.4.3-.5.1-.2 0-.4 0-.6s-.7-1.7-1-2.3c-.3-.6-.6-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.1-1.2 2.8 1.2 3.3 1.4 3.5c.2.2 2.3 3.5 5.6 4.9.8.3 1.4.5 1.9.6.8.3 1.6.2 2.2.1.7-.1 2.1-.9 2.4-1.7.3-.8.3-1.5.2-1.7-.1-.2-.3-.3-.6-.5Z"/></svg>
          </a>
          <div id="doctorStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <div class="schedule-wrap">
        <table class="schedule-grid" aria-label="Doctor schedule grid">
          <thead>
            <tr>
              <th class="corner">Time</th>
              <th>Sun</th>
              <th>Mon</th>
              <th>Tue</th>
              <th>Wed</th>
              <th>Thu</th>
            </tr>
          </thead>
          <tbody id="doctorScheduleBody"></tbody>
        </table>
      </div>

      <div class="legend">
        <span class="pill pill-r">R</span><span class="muted">Regular</span>
        <span class="pill pill-las">LAS</span><span class="muted">LAS</span>
      </div>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="panel-title-row" style="margin-bottom:8px;">
        <h2 style="margin:0;">Doctor Courses</h2>
        <div id="doctorCoursesStatus" class="status" role="status" aria-live="polite"></div>
      </div>
      <div id="doctorCoursesList" class="courses-list">
        <div class="muted">Loading courses…</div>
      </div>
    </section>
  </main>

  <script>
    // Pass doctor_id to app.js
    window.DOCTOR_ID = <?php echo json_encode($doctorId); ?>;
  </script>
  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/doctor_view.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    if (typeof window.DOCTOR_ID !== "undefined") {
      const id = Number(window.DOCTOR_ID);
      if (id > 0) {
        window.dmportal?.initDoctorView?.(id);
      }
    }
  </script>
</body>
</html>
