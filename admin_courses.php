<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_courses.php');
auth_require_roles(['admin','management']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Course Management</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('admin_courses.php'); ?>

  <main class="container">
    <header class="page-header">
      <h1>Course Management</h1>
      <p class="subtitle">Assign doctors, edit details, and manage existing courses.</p>
    </header>

    <section class="card">
      <div class="card-header">
        <div>
          <h2>Add New Course</h2>
        </div>
        <button id="refreshCoursesAdmin" class="btn btn-small btn-secondary" type="button">Refresh List</button>
      </div>

      <form id="courseForm" class="form" autocomplete="off">
        <div class="field">
          <label for="program">Program</label>
          <select id="program" name="program" required>
            <option value="Digital Marketing">Digital Marketing</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="year_level">Year Level</label>
            <select id="year_level" name="year_level" required>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
            </select>
          </div>

          <div class="field">
            <label for="semester">Semester</label>
            <select id="semester" name="semester" required>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="subject_code">Course Code</label>
            <input id="subject_code" name="subject_code" type="text" placeholder="e.g., 3.014" list="course_code_list" required />
            <datalist id="course_code_list"></datalist>
          </div>

          <div class="field">
            <label for="course_name">Course Name</label>
            <input id="course_name" name="course_name" type="text" placeholder="e.g., Introduction to Digital Marketing" list="course_name_list" required />
            <datalist id="course_name_list"></datalist>
          </div>
        </div>

        <div class="field">
          <label for="course_type">Course Type (R / LAS)</label>
          <select id="course_type" name="course_type" required>
            <option value="R">R (Regular)</option>
            <option value="LAS">LAS</option>
          </select>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="course_hours">Total Hours</label>
            <input id="course_hours" name="course_hours" type="number" min="0" step="0.5" value="10" required />
          </div>

          <div class="field">
            <label for="coefficient">Coefficient</label>
            <input id="coefficient" name="coefficient" type="number" min="0" step="0.01" value="1" required />
            <small class="hint">Decimal coefficient (e.g., 1.00, 2.50).</small>
          </div>
        </div>

        <div class="field">
          <label for="default_room_code">Default Room (optional)</label>
          <input id="default_room_code" name="default_room_code" type="text" maxlength="50" placeholder="e.g., 101 / Lab A" />
          <small class="hint">If set, it will auto-fill when scheduling this subject (still editable per slot).</small>
        </div>

        <div class="field">
          <label>Assigned Doctors</label>
          <input type="hidden" id="doctor_id" name="doctor_id" required />

          <div class="multi-select" id="createDoctorsMulti">
            <button class="multi-select-btn" type="button" aria-haspopup="true" aria-expanded="false">
              <span class="multi-select-summary" id="createDoctorsSummary">Select doctors…</span>
              <span class="multi-select-caret">▾</span>
            </button>
            <div class="multi-select-menu" role="menu" aria-label="Select doctors"></div>
          </div>

          <small class="hint">Pick one or more doctors. The first selected doctor becomes the primary assignment.</small>
          <small class="hint">Note: Remaining hours are calculated automatically from Total Hours and scheduled slots.</small>
        </div>

        <div class="actions">
          <button type="submit" class="btn">Add Course</button>
          <button type="reset" class="btn btn-secondary">Reset</button>
        </div>

        <div id="status" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>

    <section class="card mt-16">
      <div class="card-header">
        <div>
          <h2>All Courses</h2>
          <p class="card-subtitle">Edit, reassign doctors, or delete courses (cannot delete if used in schedules).</p>
        </div>
        <div class="page-actions">
          <div class="filter-bar">
            <div class="field">
              <label for="coursesYearFilter">Academic Year</label>
              <select id="coursesYearFilter" class="navlink">
                <option value="">All</option>
                <option value="1">Year 1</option>
                <option value="2">Year 2</option>
                <option value="3">Year 3</option>
              </select>
            </div>
            <div class="field">
              <label for="coursesSemesterFilter">Semester</label>
              <select id="coursesSemesterFilter" class="navlink">
                <option value="">All</option>
                <option value="1">Sem 1</option>
                <option value="2">Sem 2</option>
              </select>
            </div>
            <div class="field">
              <label for="courseSearch">Search</label>
              <input id="courseSearch" type="text" placeholder="Search by name/program…" />
            </div>
          </div>
        </div>
      </div>

      <div id="adminCoursesList" class="courses-list" aria-live="polite">
        <div class="muted">Loading courses…</div>
      </div>

      <div id="adminCoursesStatus" class="status" role="status" aria-live="polite"></div>
    </section>
  </main>

  <!-- Hour Split Modal -->
  <div id="hoursSplitModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="hoursSplitTitle">
      <div class="modal-header">
        <h3 id="hoursSplitTitle">Split Course Hours</h3>
        <button class="icon-btn" type="button" data-close="1" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="hoursSplitCourseId" value="" />
        <div class="muted mb-12" id="hoursSplitCourseMeta"></div>
        <div id="hoursSplitRows" class="grid-2" style="gap:10px;"></div>
        <div id="hoursSplitStatus" class="status" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        <button class="btn" id="hoursSplitSave" type="button">Save Split</button>
      </div>
    </div>
  </div>

  <!-- Course edit modal -->
  <div id="courseEditModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="courseEditTitle">
      <div class="modal-header">
        <h3 id="courseEditTitle">Edit Course</h3>
        <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
      </div>

      <div class="modal-body">
        <div class="field">
          <label for="edit_course_name">Course Name</label>
          <input id="edit_course_name" type="text" />
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="edit_program">Program</label>
            <input id="edit_program" type="text" />
          </div>
          <div class="field">
            <label for="edit_year_level">Year</label>
            <select id="edit_year_level">
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="edit_semester">Semester</label>
            <select id="edit_semester">
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
          <div class="field">
            <label for="edit_course_type">Type (R / LAS)</label>
            <select id="edit_course_type">
              <option value="R">R (Regular)</option>
              <option value="LAS">LAS</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="edit_subject_code">Subject Code</label>
          <input id="edit_subject_code" type="text" placeholder="e.g., 3.014" required />
          <small class="hint">Required. Will display as “R 3.014” / “LAS 3.014”.</small>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="edit_course_hours">Total Hours</label>
            <input id="edit_course_hours" type="number" step="0.5" min="0" />
          </div>

          <div class="field">
            <label for="edit_coefficient">Coefficient</label>
            <input id="edit_coefficient" type="number" min="0" step="0.01" required />
            <small class="hint">Decimal coefficient (e.g., 1.00, 2.50).</small>
          </div>
        </div>

        <div class="field">
          <label for="edit_default_room_code">Default Room (optional)</label>
          <input id="edit_default_room_code" type="text" maxlength="50" placeholder="e.g., 101 / Lab A" />
        </div>

        <div class="field">
          <label>Assigned Doctors</label>

          <div class="multi-select" id="editDoctorsMulti">
            <button class="multi-select-btn" type="button" aria-haspopup="true" aria-expanded="false">
              <span class="multi-select-summary" id="editDoctorsSummary">Select doctors…</span>
              <span class="multi-select-caret">▾</span>
            </button>
            <div class="multi-select-menu" role="menu" aria-label="Select doctors"></div>
          </div>

          <small class="hint">You can select multiple doctors. Your selection will stay visible.</small>
        </div>

        <div id="courseEditStatus" class="status" role="status" aria-live="polite"></div>
      </div>

      <div class="modal-actions">
        <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        <button id="courseEditSplitHours" class="btn btn-secondary" type="button">Split Hours</button>
        <button id="courseEditSave" class="btn" type="button">Save</button>
      </div>

      <input type="hidden" id="edit_course_id" />
    </div>
  </div>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/admin_courses.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAdminCoursesPage?.();
  </script>
</body>
</html>
