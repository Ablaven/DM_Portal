<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Attendance page:
// - Admin/Management: can take attendance for any scheduled slot.
// - Teacher: can only take attendance for their own scheduled slots (enforced in APIs).
//
// Note: This page intentionally has a Year-only filter (no semester filter).
auth_require_page_access('attendance.php');
auth_require_login();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="students-view">
  <?php render_portal_navbar('attendance.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Attendance</h1>
      <p class="subtitle">Click any scheduled slot to take attendance for that slot. Filter is Year-only.</p>
    </header>

    <section class="card">
      <div class="schedule-header">
        <div class="filter-bar">
          <div class="field">
            <label for="attendanceWeekSelect">Start Week</label>
            <select id="attendanceWeekSelect">
              <option value="">Loading…</option>
            </select>
          </div>

          <div class="field">
            <label for="attendanceCourseSelect">Course (for export)</label>
            <select id="attendanceCourseSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>

        <div class="page-actions">
          <button id="exportAttendanceXls" class="btn btn-secondary btn-small" type="button">Export Excel</button>
          <button id="refreshAttendanceGrid" class="btn btn-secondary btn-small" type="button">Refresh</button>
          <div id="attendanceStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <nav class="tabs" aria-label="Year tabs" style="margin-top:10px;">
        <button class="tab active" type="button" data-year="1">Year 1</button>
        <button class="tab" type="button" data-year="2">Year 2</button>
        <button class="tab" type="button" data-year="3">Year 3</button>
      </nav>

      <div class="schedule-wrap">
        <table class="schedule-grid" aria-label="Attendance schedule grid">
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
          <tbody id="attendanceScheduleBody"></tbody>
        </table>
      </div>

      <div class="legend">
        <span class="muted">Only scheduled slots are clickable. Teachers will only see their own slots.</span>
      </div>
    </section>

    <div id="attendanceModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle" style="width: min(1400px, 96vw);">
        <div class="modal-header">
          <h3 id="attendanceModalTitle">Take Attendance</h3>
          <button class="btn btn-secondary btn-small" type="button" data-close="1">Close</button>
        </div>

        <div class="modal-body">
          <div class="muted" id="attendanceModalMeta"></div>

          <div class="field">
            <label for="attendanceStudentSearch">Search</label>
            <input id="attendanceStudentSearch" type="text" placeholder="Type a student name…" />
          </div>

          <div class="modal-actions" style="justify-content:flex-start;">
            <button id="attendanceMarkAllPresent" class="btn btn-small" type="button">Mark all Present</button>
            <button id="attendanceMarkAllAbsent" class="btn btn-small btn-secondary" type="button">Mark all Absent</button>
            <button id="attendanceSaveChanges" class="btn btn-small btn-primary" type="button">Save</button>
            <button id="attendanceCopyNextLecture" class="btn btn-small btn-secondary" type="button">Copy to next lecture</button>
          </div>

          <div class="schedule-wrap">
            <table class="schedule-grid" aria-label="Attendance list">
              <thead>
                <tr>
                  <th style="width:160px;">ID</th>
                  <th style="width:320px;">Student Name</th>
                  <th style="width:140px;">Attendence</th>
                </tr>
              </thead>
              <tbody id="attendanceModalBody"></tbody>
            </table>
          </div>

          <div id="attendanceModalStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>
    </div>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/attendance.js?v=20260222"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAttendancePage?.();
  </script>
</body>
</html>
