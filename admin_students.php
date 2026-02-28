<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_students.php');
auth_require_roles(['admin','management']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Management</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('admin_students.php'); ?>

  <main class="container">
    <header class="page-header">
      <h1>Student Management</h1>
      <p class="subtitle">Add, edit, and remove students (name, email, and Student ID).</p>
    </header>

    <section class="card">
      <div class="card-header">
        <h2 style="margin:0;">Add Student</h2>
        <button id="refreshStudentsAdmin" class="btn btn-small btn-secondary" type="button">Refresh List</button>
      </div>

      <form id="studentForm" class="form" autocomplete="off">
        <div class="grid-2">
          <div class="field">
            <label for="student_full_name">Full Name</label>
            <input id="student_full_name" name="full_name" type="text" placeholder="e.g., Omar Ahmed" required />
          </div>
          <div class="field">
            <label for="student_email">Email</label>
            <input id="student_email" name="email" type="email" placeholder="student@email.com" required />
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="student_code">Student ID</label>
            <input id="student_code" name="student_code" type="text" placeholder="e.g., 2026-001" required />
            <small class="hint">This is your manual student ID/code.</small>
          </div>

          <div class="field">
            <label for="student_program">Program</label>
            <select id="student_program" name="program" required>
              <option value="Digital Marketing">Digital Marketing</option>
              <option value="Other">Other</option>
            </select>
            <small class="hint">Students are tied to a program.</small>
          </div>

          <div class="field">
            <label for="student_year_level">Year</label>
            <select id="student_year_level" name="year_level" required>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
            <small class="hint">Students apply to both semesters.</small>
          </div>
        </div>

        <div class="actions">
          <button type="submit" class="btn">Add Student</button>
          <button type="reset" class="btn btn-secondary">Reset</button>
        </div>

        <div id="studentStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="card-header">
        <div>
          <h2 style="margin:0;">All Students</h2>
          <div class="muted" style="margin-top:4px;">Use the Academic Year filter to narrow the list.</div>
        </div>

        <div class="page-actions">
          <div class="field" style="margin:0; min-width:160px;">
            <label for="studentsYearFilter" class="muted" style="font-size:0.85rem;">Academic Year</label>
            <select id="studentsYearFilter" class="navlink" style="padding:7px 10px;">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field" style="margin:0; min-width:260px;">
            <label for="studentSearch" class="muted" style="font-size:0.85rem;">Search</label>
            <input id="studentSearch" type="text" placeholder="Search by name/email/ID…" />
          </div>
        </div>
      </div>

      <div id="adminStudentsList" class="courses-list" aria-live="polite">
        <div class="muted">Loading students…</div>
      </div>

      <div id="adminStudentsStatus" class="status" role="status" aria-live="polite"></div>
    </section>

    <!-- Student edit modal -->
    <div id="studentEditModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="studentEditTitle">
        <div class="modal-header">
          <h3 id="studentEditTitle">Edit Student</h3>
          <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
        </div>

        <div class="modal-body">
          <div class="field">
            <label for="edit_student_full_name">Full Name</label>
            <input id="edit_student_full_name" type="text" />
          </div>

          <div class="field">
            <label for="edit_student_email">Email</label>
            <input id="edit_student_email" type="email" />
          </div>

          <div class="grid-2">
            <div class="field" style="margin:0;">
              <label for="edit_student_code">Student ID</label>
              <input id="edit_student_code" type="text" />
            </div>

            <div class="field" style="margin:0;">
              <label for="edit_student_program">Program</label>
              <select id="edit_student_program">
                <option value="Digital Marketing">Digital Marketing</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="field" style="margin:0;">
              <label for="edit_student_year_level">Year</label>
              <select id="edit_student_year_level">
                <option value="1">Year 1</option>
                <option value="2">Year 2</option>
                <option value="3">Year 3</option>
              </select>
              <small class="hint">Applies to both semesters.</small>
            </div>
          </div>

          <div id="studentEditStatus" class="status" role="status" aria-live="polite"></div>
        </div>

        <div class="modal-actions">
          <button id="studentEditSave" class="btn" type="button">Save</button>
          <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        </div>

        <input type="hidden" id="edit_student_id" />
      </div>
    </div>

  </main>

  <script src="js/core.js?v=20260228f"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/admin_students.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAdminStudentsPage?.();
  </script>
</body>
</html>
