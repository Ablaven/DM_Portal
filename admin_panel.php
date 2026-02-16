<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_panel.php');
auth_require_roles(['admin']);

$importStatus = $_GET['import_status'] ?? '';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Panel</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body>
  <?php render_portal_navbar('admin_panel.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Admin Panel</h1>
      <p class="subtitle">Central place for admin-only tools and utilities.</p>
    </header>

    <section class="card">
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Database Tools</h2>
          <div class="muted" style="margin-top:4px;">Export or import a full SQL dump (admin only).</div>
        </div>
      </div>

      <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <a class="btn btn-secondary" href="php/export_database_sql.php">Export Database SQL</a>
        <form class="field" action="php/import_database_sql.php" method="post" enctype="multipart/form-data" style="margin:0;">
          <label class="muted" style="font-size:0.85rem;" for="importSqlFile">Import SQL</label>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input id="importSqlFile" name="sql_file" type="file" accept=".sql" required />
            <button class="btn btn-secondary" type="submit">Upload</button>
          </div>
        </form>
        <?php if ($importStatus === 'success') : ?>
          <div class="status success" role="status">Import completed successfully.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" style="margin-top:20px;">
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Semester Management</h2>
          <div class="muted" style="margin-top:4px;">Create semesters, activate the current semester, and reset weeks (this does not advance students yet).</div>
        </div>
      </div>

      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;">
        <label style="display:flex; flex-direction:column; gap:6px; min-width:200px;">
          <span class="muted" style="font-size:0.85rem;">Academic Year</span>
          <select id="academicYearSelect" class="navlink" style="padding:8px 10px;"></select>
        </label>
        <label style="display:flex; flex-direction:column; gap:6px; min-width:200px;">
          <span class="muted" style="font-size:0.85rem;">Quick Activate Term</span>
          <select id="termSelect" class="navlink" style="padding:8px 10px;"></select>
        </label>
        <button id="activateSelectedTerm" class="btn btn-secondary" type="button">Activate Selected Term</button>
      </div>

      <form id="termCreateForm" class="field" style="margin-bottom:16px;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:0.85rem;">Label</span>
            <input name="label" type="text" placeholder="Semester 1 (2026)" required />
          </label>
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:0.85rem;">Semester #</span>
            <select name="semester" required>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </label>
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:0.85rem;">Start Date</span>
            <input name="start_date" type="date" />
          </label>
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:0.85rem;">End Date</span>
            <input name="end_date" type="date" />
          </label>
          <button class="btn btn-secondary" type="submit">Create Semester</button>
        </div>
      </form>

      <div id="termStatus" class="status" role="status" style="margin-bottom:12px;"></div>

      <div class="table-wrap">
        <table id="termsTable" class="data-table">
          <thead>
            <tr>
              <th>Label</th>
              <th>Semester</th>
              <th>Status</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <section class="card" style="margin-top:20px;">
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Advance Semester / Year</h2>
          <div class="muted" style="margin-top:4px;">One-click advance: semester 1 → semester 2 (weeks reset). Semester 2 → new academic year + student advancement + weeks reset.</div>
        </div>
      </div>

      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div class="field" style="margin:0;">
          <label for="advanceStartDate" class="muted" style="font-size:0.85rem;">Start date for new Week 1</label>
          <div style="display:flex; gap:6px; align-items:center;">
            <input id="advanceStartDate" class="navlink" style="padding:8px 10px;" type="date" />
          </div>
        </div>
        <button id="advanceTermButton" class="btn" type="button">Advance to Next Semester / Year</button>
        <button id="customAdvanceButton" class="btn btn-secondary" type="button">Custom Student Advance…</button>
        <div id="advanceStatus" class="status" role="status"></div>
      </div>
    </section>

    <div id="customAdvanceModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="customAdvanceTitle">
        <div class="modal-header">
          <h3 id="customAdvanceTitle">Custom Student Advance</h3>
          <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
        </div>

        <div class="modal-body">
          <div class="field" style="margin-bottom:12px;">
            <label for="customAdvanceStartDate">Start date for new Week 1</label>
            <input id="customAdvanceStartDate" type="date" />
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <div class="field" style="margin:0; min-width:200px;">
              <label for="customAdvanceProgram">Program</label>
              <select id="customAdvanceProgram" class="navlink" style="padding:8px 10px;">
                <option value="">All Programs</option>
              </select>
            </div>
            <div class="field" style="margin:0; min-width:160px;">
              <label for="customAdvanceYear">Year Level</label>
              <select id="customAdvanceYear" class="navlink" style="padding:8px 10px;">
                <option value="">All Years</option>
                <option value="1">Year 1</option>
                <option value="2">Year 2</option>
                <option value="3">Year 3</option>
              </select>
            </div>
          </div>

          <div class="field" style="margin-bottom:12px;">
            <label for="customAdvanceSearch">Search Students</label>
            <input id="customAdvanceSearch" type="text" placeholder="Search by name/email/code…" />
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px;">
            <div class="field" style="margin:0; min-width:220px;">
              <label for="customAdvancePreset">Rule Preset</label>
              <select id="customAdvancePreset" class="navlink" style="padding:8px 10px;">
                <option value="">Select preset…</option>
                <option value="advance_all">Advance all (year +1)</option>
                <option value="repeat_all">Repeat all</option>
                <option value="graduate_final">Graduate final year only</option>
                <option value="advance_except_final">Advance all, graduate final year</option>
              </select>
            </div>
            <button id="applyAdvancePreset" class="btn btn-secondary" type="button">Apply Preset</button>
          </div>

          <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
            <button id="bulkAdvanceAll" class="btn btn-secondary" type="button">Set All to Advance</button>
            <button id="bulkRepeatAll" class="btn btn-secondary" type="button">Set All to Repeat</button>
            <button id="bulkGraduateAll" class="btn btn-secondary" type="button">Set All to Graduate</button>
          </div>

          <div class="table-wrap" style="max-height:360px; overflow:auto;">
            <table class="data-table" id="customAdvanceTable">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Year</th>
                  <th>Action</th>
                  <th>New Year</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div id="customAdvanceStatus" class="status" role="status" style="margin-top:10px;"></div>
        </div>

        <div class="modal-actions">
          <button id="customAdvanceSubmit" class="btn" type="button">Run Custom Advance</button>
          <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        </div>
      </div>
    </div>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/admin_terms.js?v=20260213"></script>
  <script src="js/admin_advance.js?v=20260213"></script>
  <script>
    window.dmportal?.initNavbar?.({});
  </script>
</body>
</html>
