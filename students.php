<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Student schedule page.
auth_require_page_access('students.php');

// Students can access; admins too.
auth_require_roles(['admin','management','student']);

// Combined student schedule by Program + Year (Sun–Thu).
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Schedule</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="students-view">
  <?php render_portal_navbar('students.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Student Schedule</h1>
      <p class="subtitle">Combined schedule across all doctors (filtered by Program + Year)</p>
    </header>

    <section class="card">
      <div class="schedule-header" style="align-items:flex-end;">
        <div class="controls" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
          <div class="field" style="min-width:220px;">
            <label for="studentProgram">Program</label>
            <select id="studentProgram">
              <option value="Digital Marketing">Digital Marketing</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="field" style="min-width:180px;">
            <label for="studentSemester">Semester</label>
            <select id="studentSemester">
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>

          <div class="field" style="min-width:200px;">
            <label for="studentWeekSelect">Week</label>
            <select id="studentWeekSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
          <button id="exportStudentXls" class="btn btn-secondary btn-small" type="button">Export Excel (.xlsx)</button>
          <?php $u = auth_current_user(); ?>
          <?php if (($u['role'] ?? '') === 'admin' || ($u['role'] ?? '') === 'management') : ?>
            <button id="emailStudentSchedule" class="icon-btn" type="button" title="Email student schedule" aria-label="Email student schedule">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></svg>
            </button>
          <?php endif; ?>
          <div id="studentStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <nav class="tabs" aria-label="Year tabs" style="margin-top:10px;">
        <button class="tab active" type="button" data-year="1">Year 1</button>
        <button class="tab" type="button" data-year="2">Year 2</button>
        <button class="tab" type="button" data-year="3">Year 3</button>
      </nav>

      <div class="schedule-wrap">
        <table class="schedule-grid" aria-label="Student schedule grid">
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
          <tbody id="studentScheduleBody"></tbody>
        </table>
      </div>

      <div class="legend">
        <span class="pill pill-r">R</span><span class="muted">Regular</span>
        <span class="pill pill-las">LAS</span><span class="muted">LAS</span>
        <span class="muted">If multiple lectures exist in the same slot (different doctors), it will show “Multiple”.</span>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/students.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initStudentView?.();
  </script>
</body>
</html>
