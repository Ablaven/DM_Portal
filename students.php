<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Student schedule page.
auth_require_page_access('students.php');

// Students can access; admins too.
auth_require_roles(['admin','management','student']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$studentId = (int)($u['student_id'] ?? 0);
$isStudent = ($role === 'student' && $studentId > 0);

// If logged in as a student, fetch their year/program
$studentYear = null;
$studentProgram = null;
$studentSemester = null;

if ($isStudent) {
    require_once __DIR__ . '/php/db_connect.php';
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT year_level, program FROM students WHERE student_id = :id LIMIT 1');
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();
    if ($student) {
        $studentYear = (int)$student['year_level'];
        $studentProgram = (string)$student['program'];
        // Semester is determined by the active term, we'll handle that in JavaScript
    }
}

// Combined student schedule by Program + Year (Sun–Thu).
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Schedule</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="students-view">
  <?php render_portal_navbar('students.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Student Schedule</h1>
      <?php if ($isStudent): ?>
        <p class="subtitle">Your schedule</p>
      <?php else: ?>
        <p class="subtitle">Combined schedule across all doctors (filtered by Program + Year)</p>
      <?php endif; ?>
    </header>

    <section class="card">
      <div class="schedule-header">
        <?php if (!$isStudent): ?>
        <div class="filter-bar">
          <div class="field">
            <label for="studentProgram">Program</label>
            <select id="studentProgram">
              <option value="Digital Marketing">Digital Marketing</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="field">
            <label for="studentSemester">Semester</label>
            <select id="studentSemester">
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>

          <div class="field">
            <label for="studentWeekSelect">Week</label>
            <select id="studentWeekSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>
        <?php else: ?>
        <div class="filter-bar">
          <div class="field">
            <label for="studentWeekSelect">Week</label>
            <select id="studentWeekSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>
        <?php endif; ?>

        <div class="page-actions">
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

      <?php if (!$isStudent): ?>
      <nav class="tabs" aria-label="Year tabs" style="margin-top:10px;">
        <button class="tab active" type="button" data-year="1">Year 1</button>
        <button class="tab" type="button" data-year="2">Year 2</button>
        <button class="tab" type="button" data-year="3">Year 3</button>
      </nav>
      <?php endif; ?>

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
      </div></section>
  </main>

  <script src="js/core.js?v=20260919f"></script>
  <script src="js/navbar.js?v=20260919f"></script>
  <script src="js/students.js?v=20260919f"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initStudentView?.({
      isStudent: <?php echo $isStudent ? 'true' : 'false'; ?>,
      studentYear: <?php echo $studentYear ?? 'null'; ?>,
      studentProgram: <?php echo json_encode($studentProgram ?? null); ?>
    });
  </script>
</body>
</html>
