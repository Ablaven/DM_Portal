<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_users.php');
auth_require_roles(['admin']);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Accounts</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
  <link rel="stylesheet" href="css/admin_users.css?v=20260105" />
</head>
<body>
  <?php render_portal_navbar('admin_users.php'); ?>

  <main class="container">
    <header class="page-header">
      <h1>User Accounts</h1>
      <p class="subtitle">Create portal logins for doctors and students and assign which pages they can access.</p>
    </header>

    <section class="card">
      <div class="panel-title-row" style="margin-bottom:10px;">
        <h2 style="margin:0;">Create User</h2>
        <button id="refreshUsers" class="btn btn-small btn-secondary" type="button">Refresh</button>
      </div>

      <form id="createUserForm" class="form" autocomplete="off">
        <div class="grid-2">
          <div class="field">
            <label for="u_username">Username</label>
            <input id="u_username" name="username" type="text" required />
          </div>
          <div class="field">
            <label for="u_password">Password</label>
            <input id="u_password" name="password" type="password" required />
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="u_role">Role</label>
            <select id="u_role" name="role" required>
              <option value="teacher">Teacher (Doctor)</option>
              <option value="student">Student</option>
              <option value="management">Management</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="field">
            <label>Allowed pages</label>
            <div id="u_allowed" class="allowed-pages" role="group" aria-label="Allowed pages">
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="index.php" /> Dashboard</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="schedule_builder.php" /> Schedule Builder</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="admin_courses.php" /> Course Management</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="admin_doctors.php" /> Doctor Management</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="admin_students.php" /> Student Management</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="admin_users.php" /> User Accounts</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="admin_panel.php" /> Admin Panel</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="doctor.php" /> Doctor Schedule Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="availability.php" /> Availability Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="students.php" /> Student Schedule Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="attendance.php" /> Attendance Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="evaluation.php" /> Evaluation Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="student_dashboard.php" /> Student Dashboard Page</label>
              <label class="chk"><input type="checkbox" name="allowed_pages[]" value="profile.php" /> Profile</label>
            </div>
            <small class="hint">Leave all unchecked to use role defaults (teacher→doctor.php, student→students.php). Admin always has full access.</small>
          </div>
        </div>

        <div class="grid-2">
          <div class="field" id="u_doctor_id_wrap">
            <label for="u_doctor_id">Doctor ID</label>
            <input id="u_doctor_id" name="doctor_id" type="number" min="1" placeholder="Doctor ID" />
          </div>
          <div class="field" id="u_student_id_wrap">
            <label for="u_student_id">Student ID</label>
            <input id="u_student_id" name="student_id" type="number" min="1" placeholder="Student ID" />
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Create</button>
          <button class="btn btn-secondary" type="reset">Reset</button>
        </div>
        <div id="createUserStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="panel-title-row" style="margin-bottom:10px; flex-wrap:wrap; gap:12px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Existing Users</h2>
          <div class="muted" style="margin-top:4px;">Search by username, role, doctor_id, student_id.</div>
        </div>

        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; justify-content:flex-end;">
          <div class="field" style="margin:0; min-width:260px;">
            <label for="userSearch" class="muted" style="font-size:0.85rem;">Search</label>
            <input id="userSearch" type="text" placeholder="Search users…" />
          </div>
        </div>

        <div id="usersStatus" class="status" role="status" aria-live="polite"></div>
      </div>
      <div id="usersList" class="courses-list"><div class="muted">Loading…</div></div>
    </section>

    <!-- Edit User modal -->
    <div id="userEditModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="userEditTitle" style="width:min(900px,98vw);">
        <div class="modal-header">
          <h3 id="userEditTitle">Edit User</h3>
          <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
        </div>

        <div class="modal-body">
          <div class="grid-2">
            <div class="field">
              <label for="edit_user_username">Username</label>
              <input id="edit_user_username" type="text" />
            </div>
            <div class="field">
              <label for="edit_user_role">Role</label>
              <select id="edit_user_role">
                <option value="admin">Admin</option>
                <option value="management">Management</option>
                <option value="teacher">Teacher</option>
                <option value="student">Student</option>
              </select>
            </div>
          </div>

          <div class="grid-2">
            <div class="field" id="edit_user_doctor_id_wrap">
              <label for="edit_user_doctor_id">Doctor ID</label>
              <input id="edit_user_doctor_id" type="number" min="1" />
            </div>
            <div class="field" id="edit_user_student_id_wrap">
              <label for="edit_user_student_id">Student ID</label>
              <input id="edit_user_student_id" type="number" min="1" />
            </div>
          </div>

          <div class="field">
            <label>Allowed pages</label>
            <div id="edit_user_allowed" class="allowed-pages" role="group" aria-label="Allowed pages">
              <label class="chk"><input type="checkbox" value="index.php" /> Dashboard</label>
              <label class="chk"><input type="checkbox" value="schedule_builder.php" /> Schedule Builder</label>
              <label class="chk"><input type="checkbox" value="admin_courses.php" /> Course Management</label>
              <label class="chk"><input type="checkbox" value="admin_doctors.php" /> Doctor Management</label>
              <label class="chk"><input type="checkbox" value="admin_students.php" /> Student Management</label>
              <label class="chk"><input type="checkbox" value="admin_users.php" /> User Accounts</label>
              <label class="chk"><input type="checkbox" value="admin_panel.php" /> Admin Panel</label>
              <label class="chk"><input type="checkbox" value="doctor.php" /> Doctor Schedule Page</label>
              <label class="chk"><input type="checkbox" value="students.php" /> Student Schedule Page</label>
              <label class="chk"><input type="checkbox" value="attendance.php" /> Attendance Page</label>
              <label class="chk"><input type="checkbox" value="availability.php" /> Availability Page</label>
              <label class="chk"><input type="checkbox" value="evaluation.php" /> Evaluation Page</label>
              <label class="chk"><input type="checkbox" value="student_dashboard.php" /> Student Dashboard Page</label>
              <label class="chk"><input type="checkbox" value="profile.php" /> Profile</label>
            </div>
            <small class="hint">Leave all unchecked to use role defaults. Admin always has full access.</small>
          </div>

          <div class="grid-2">
            <div class="field">
              <label for="edit_user_active">Active</label>
              <select id="edit_user_active">
                <option value="1">Active</option>
                <option value="0">Disabled</option>
              </select>
            </div>
            <div class="field">
              <label for="edit_user_new_password">Reset password</label>
              <input id="edit_user_new_password" type="password" placeholder="Leave blank to keep" />
            </div>
          </div>

          <div id="userEditStatus" class="status" role="status" aria-live="polite"></div>
        </div>

        <div class="modal-actions">
          <button id="userEditSave" class="btn" type="button">Save</button>
          <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        </div>

        <input type="hidden" id="edit_user_id" />
      </div>
    </div>

  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/admin_users.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAdminUsersPage?.();
  </script>
</body>
</html>
