<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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
              <option value="">Loadingâ€¦</option>
            </select>
          </div>

          <div class="field">
            <label for="attendanceCourseSelect">Course (for export)</label>
            <select id="attendanceCourseSelect">
              <option value="">Loadingâ€¦</option>
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
      <div class="modal-card attendance-modal" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
        <div class="modal-header attendance-modal-header">
          <div>
            <h3 id="attendanceModalTitle">Take Attendance</h3>
            <div class="muted" id="attendanceModalMeta" style="margin-top:6px; font-size:0.875rem;"></div>
          </div>
          <button class="btn btn-secondary btn-small" type="button" data-close="1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
            Close
          </button>
        </div>

        <div class="modal-body">
          <div class="attendance-controls">
            <div class="field" style="flex:1; margin:0;">
              <label for="attendanceStudentSearch" style="font-size:0.875rem; margin-bottom:6px;">Search Students</label>
              <input id="attendanceStudentSearch" type="text" placeholder="Type student name or ID..." style="width:100%;" />
            </div>

            <div class="attendance-quick-actions">
              <button id="attendanceMarkAllPresent" class="btn btn-small btn-success" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle; margin-right:4px;">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                All Present
              </button>
              <button id="attendanceMarkAllAbsent" class="btn btn-small btn-secondary" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M15 9l-6 6M9 9l6 6"/>
                </svg>
                All Absent
              </button>
            </div>
          </div>

          <div class="attendance-table-wrapper">
            <table class="attendance-table" aria-label="Attendance list">
              <thead>
                <tr>
                  <th style="width:160px;">Student ID</th>
                  <th style="width:auto; text-align:left;">Student Name</th>
                  <th style="width:180px; text-align:center;">Attendance Status</th>
                </tr>
              </thead>
              <tbody id="attendanceModalBody"></tbody>
            </table>
          </div>

          <div id="attendanceModalStatus" class="status" role="status" aria-live="polite"></div>
        </div>

        <div class="modal-footer attendance-modal-footer">
          <button id="attendanceCopyNextLecture" class="btn btn-secondary" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
              <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
            </svg>
            Copy to Next Lecture
          </button>
          <button id="attendanceSaveChanges" class="btn btn-primary" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle; margin-right:4px;">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Save Attendance
          </button>
        </div>
      </div>
    </div>
  </main>

  <script src="js/core.js?v=20260425a"></script>
  <script src="js/navbar.js?v=20260425a"></script>
  <script src="js/attendance.js?v=20260919z"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAttendancePage?.();
  </script>
</body>
</html>
