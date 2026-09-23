<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_doctors.php');
auth_require_roles(['admin','management']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor Management</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('admin_doctors.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <div>
        <h1>Doctor Management</h1>
        <p class="subtitle">Add, edit, and manage professors with their academic information.</p>
      </div>
    </header>

    <!-- Summary Stats -->
    <section class="card" style="margin-bottom:20px;">
      <div style="padding:14px 16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:8px;">
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Doctors</div>
            <div style="font-size:1.5rem; font-weight:700;" id="doctorsTotalCount">—</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Egyptian</div>
            <div style="font-size:1.5rem; font-weight:700; color:#10b981;" id="doctorsEgyptianCount">—</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">French</div>
            <div style="font-size:1.5rem; font-weight:700; color:#3b82f6;" id="doctorsFrenchCount">—</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">With Phone</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="doctorsWithPhoneCount">—</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
          <h2 style="margin:0 0 6px;">Add Doctor</h2>
          <p class="muted" style="margin:0; font-size:0.9rem;">Enter professor details to add them to the system.</p>
        </div>
        <button id="refreshDoctorsAdmin" class="btn btn-small btn-secondary" type="button">Refresh</button>
      </div>

      <form id="doctorForm" class="form" autocomplete="off">
        <div class="grid-2">
          <div class="field">
            <label for="doctor_full_name">Full Name</label>
            <input id="doctor_full_name" name="full_name" type="text" placeholder="e.g., Dr. Ahmed Ali" required />
          </div>
          <div class="field">
            <label for="doctor_email">Email</label>
            <input id="doctor_email" name="email" type="email" placeholder="name@university.edu" required />
          </div>
        </div>

        <div class="field">
          <label for="doctor_phone">Telephone (WhatsApp)</label>
          <input id="doctor_phone" name="phone_number" type="tel" placeholder="e.g., +2010 1234 5678" maxlength="32" />
          <small class="hint">Optional. Used for WhatsApp export.</small>
        </div>

        <div class="field">
          <label for="doctor_type">Doctor Type</label>
          <select id="doctor_type" name="doctor_type">
            <option value="Egyptian">Egyptian</option>
            <option value="French">French</option>
          </select>
        </div>

        <div class="field">
          <label for="doctor_color">Base Color (fallback)</label>
          <input id="doctor_color" name="color_code" type="color" value="#0055A4" />
          <small class="hint">Fallback color if no year-specific color is set.</small>
        </div>

        <div class="field">
          <label>Year-specific Colors</label>
          <div class="grid-3" style="gap:10px;">
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="doctor_color_y1">Year 1</label>
              <input id="doctor_color_y1" type="color" value="#0055A4" />
            </div>
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="doctor_color_y2">Year 2</label>
              <input id="doctor_color_y2" type="color" value="#0055A4" />
            </div>
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="doctor_color_y3">Year 3</label>
              <input id="doctor_color_y3" type="color" value="#0055A4" />
            </div>
          </div>
          <small class="hint">Optional. If set, schedule coloring uses the course Year9 color for this doctor.</small>
        </div>

        <div class="actions">
          <button type="submit" class="btn">Add Doctor</button>
          <button type="reset" class="btn btn-secondary">Reset</button>
        </div>

        <div id="doctorStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>

    <section class="card" style="margin-top:20px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:16px; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 6px;">All Doctors</h2>
          <p class="muted" style="margin:0; font-size:0.9rem;">Edit details or search by name and email.</p>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <div class="field" style="margin:0; min-width:160px;">
            <label for="doctorsWeekSelect" style="font-size:0.85rem; margin-bottom:4px;">Week</label>
            <select id="doctorsWeekSelect" class="navlink" style="padding:9px 11px;">
              <option value="">Loading…</option>
            </select>
          </div>
          <div class="field" style="margin:0; min-width:220px;">
            <label for="doctorSearch" style="font-size:0.85rem; margin-bottom:4px;">Search</label>
            <input id="doctorSearch" type="text" placeholder="Search doctors…" />
          </div>
        </div>
      </div>

      <div id="adminDoctorsList" class="courses-list" aria-live="polite">
        <div class="muted">Loading doctors…</div>
      </div>

      <div id="adminDoctorsStatus" class="status" role="status" aria-live="polite"></div>
    </section>

    <!-- Doctor edit modal -->
    <div id="doctorEditModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="doctorEditTitle">
        <div class="modal-header">
          <h3 id="doctorEditTitle">Edit Doctor</h3>
          <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
        </div>

        <div class="modal-body">
          <div class="field">
            <label for="edit_doctor_full_name">Full Name</label>
            <input id="edit_doctor_full_name" type="text" />
          </div>

          <div class="field">
            <label for="edit_doctor_email">Email</label>
            <input id="edit_doctor_email" type="email" />
          </div>

          <div class="field">
            <label for="edit_doctor_phone">Telephone (WhatsApp)</label>
            <input id="edit_doctor_phone" type="tel" maxlength="32" placeholder="e.g., +2010 1234 5678" />
          </div>

          <div class="field">
            <label for="edit_doctor_type">Doctor Type</label>
            <select id="edit_doctor_type">
              <option value="Egyptian">Egyptian</option>
              <option value="French">French</option>
            </select>
          </div>

          <div class="field">
            <label for="edit_doctor_color">Base Color (fallback)</label>
            <input id="edit_doctor_color" type="color" />
          </div>

          <div class="field">
            <label>Year-specific Colors</label>
            <div class="grid-3" style="gap:10px;">
              <div class="field" style="margin:0;">
                <label class="muted" style="font-size:0.85rem;" for="edit_doctor_color_y1">Year 1</label>
                <input id="edit_doctor_color_y1" type="color" />
              </div>
              <div class="field" style="margin:0;">
                <label class="muted" style="font-size:0.85rem;" for="edit_doctor_color_y2">Year 2</label>
                <input id="edit_doctor_color_y2" type="color" />
              </div>
              <div class="field" style="margin:0;">
                <label class="muted" style="font-size:0.85rem;" for="edit_doctor_color_y3">Year 3</label>
                <input id="edit_doctor_color_y3" type="color" />
              </div>
            </div>
            <small class="hint">Optional. If set, schedule coloring uses the course Year9 color for this doctor.</small>
          </div>

          <div id="doctorEditStatus" class="status" role="status" aria-live="polite"></div>
        </div>

        <div class="modal-actions">
          <button id="doctorEditSave" class="btn" type="button">Save</button>
          <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        </div>

        <input type="hidden" id="edit_doctor_id" />
      </div>
    </div>

  </main>

  <script src="js/core.js?v=20260425a"></script>
  <script src="js/navbar.js?v=20260425a"></script>
  <script src="js/admin_doctors.js?v=20260425a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAdminDoctorsPage?.();
  </script>
</body>
</html>
