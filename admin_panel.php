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

    <!-- ── Semester Wizard ─────────────────────────────────────────────────── -->
    <section class="card" id="semesterWizard" style="margin-top:20px;">

      <!-- Header: always visible, shows where you are -->
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:22px;">
        <div>
          <div class="muted" style="font-size:0.78rem; letter-spacing:.06em; text-transform:uppercase; margin-bottom:3px;">Semester Management</div>
          <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <strong id="wizardCurrentLabel" style="font-size:1.1rem;">Loading…</strong>
            <span id="wizardNextHint" class="muted" style="font-size:0.88rem;"></span>
          </div>
        </div>
        <button id="wizManualBtn" class="btn btn-small btn-secondary" type="button" style="white-space:nowrap;">⚙ Manual</button>
      </div>

      <!-- Step panels -->

      <!-- STEP 0: Idle -->
      <div id="wizStep0" class="wiz-step">
        <p id="wizStep0Hint" class="muted" style="margin:0 0 18px; font-size:0.95rem; max-width:520px;"></p>
        <button id="wizPrimaryBtn" class="btn" type="button" style="font-size:1rem; padding:11px 28px; min-width:220px;"></button>
        <div id="wizStep0Status" class="status" role="status" style="margin-top:14px;"></div>
      </div>

      <!-- STEP 1: Pick date -->
      <div id="wizStep1" class="wiz-step" style="display:none;">
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;" id="wizStep1Heading">When does the new Week 1 start?</p>
        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
          <div class="field" style="margin:0;">
            <label for="wizStartDate" style="font-size:0.83rem; margin-bottom:4px;">Start date</label>
            <input id="wizStartDate" type="date" style="padding:9px 11px;" />
          </div>
          <button id="wizStep1Next" class="btn" type="button">Continue</button>
          <button id="wizStep1Cancel" class="btn btn-secondary" type="button">Cancel</button>
        </div>
        <div id="wizStep1Status" class="status" role="status" style="margin-top:10px;"></div>
      </div>

      <!-- STEP 2a: Sem 1→2 confirm -->
      <div id="wizStep2a" class="wiz-step" style="display:none;">
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;">Ready to advance — here's what will happen:</p>
        <div id="wizStep2aSummary" style="background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px; padding:16px 18px; margin-bottom:18px; line-height:2;"></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="wizStep2aConfirm" class="btn" type="button">Advance to Semester 2</button>
          <button id="wizStep2aBack" class="btn btn-secondary" type="button">← Back</button>
        </div>
        <div id="wizStep2aStatus" class="status" role="status" style="margin-top:10px;"></div>
      </div>

      <!-- STEP 2b: Sem 2→Year — student rule -->
      <div id="wizStep2b" class="wiz-step" style="display:none;">
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;">What should happen to students?</p>
        <div class="field" style="margin:0 0 16px; max-width:360px;">
          <label for="wizStudentPreset" style="font-size:0.83rem; margin-bottom:4px;">Student advancement rule</label>
          <select id="wizStudentPreset" class="navlink" style="padding:9px 11px; width:100%;">
            <option value="advance_except_final">Advance all · graduate final year (recommended)</option>
            <option value="advance_all">Advance everyone by one year</option>
            <option value="repeat_all">Keep everyone in their current year</option>
            <option value="custom">Set each student individually…</option>
          </select>
        </div>

        <!-- Per-student table, shown only for custom -->
        <div id="wizCustomStudentPanel" style="display:none; margin-bottom:16px;">
          <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
            <input id="wizStudentSearch" type="text" placeholder="Search by name or code…" style="padding:7px 10px; flex:1; min-width:160px;" />
            <select id="wizStudentFilterYear" class="navlink" style="padding:7px 10px;">
              <option value="">All Years</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="table-wrap" style="max-height:280px; overflow:auto; border:1px solid var(--card-border); border-radius:8px;">
            <table class="data-table" id="wizStudentTable">
              <thead><tr><th>Student</th><th>Current Year</th><th>Action</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="wizStep2bNext" class="btn" type="button">Continue</button>
          <button id="wizStep2bBack" class="btn btn-secondary" type="button">← Back</button>
          <button id="wizStep2bCancel" class="btn btn-secondary" type="button">Cancel</button>
        </div>
        <div id="wizStep2bStatus" class="status" role="status" style="margin-top:10px;"></div>
      </div>

      <!-- STEP 3b: Year advance confirm -->
      <div id="wizStep3b" class="wiz-step" style="display:none;">
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;">Ready to advance — here's what will happen:</p>
        <div id="wizStep3bSummary" style="background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px; padding:16px 18px; margin-bottom:18px; line-height:2;"></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="wizStep3bConfirm" class="btn" type="button">Start New Academic Year</button>
          <button id="wizStep3bBack" class="btn btn-secondary" type="button">← Back</button>
        </div>
        <div id="wizStep3bStatus" class="status" role="status" style="margin-top:10px;"></div>
      </div>

    </section>

    <!-- ── Manual Options (collapsed) ──────────────────────────────────────── -->
    <details id="manualOptionsPanel" style="margin-top:14px;">
      <summary style="cursor:pointer; font-weight:600; padding:10px 0; user-select:none; list-style:none; display:flex; align-items:center; gap:8px; font-size:0.95rem;">
        ⚙ Manual Options
        <span class="muted" style="font-weight:400; font-size:0.83rem;">(activate, create, or reset semesters)</span>
      </summary>

      <div class="card" style="margin-top:10px;">

        <!-- Activate -->
        <h3 style="margin:0 0 12px; font-size:0.95rem; font-weight:600;">Activate a Semester</h3>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:6px;">
          <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
            Academic Year
            <select id="academicYearSelect" class="navlink" style="padding:8px 10px; min-width:170px;"></select>
          </label>
          <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
            Semester
            <select id="termSelect" class="navlink" style="padding:8px 10px; min-width:170px;"></select>
          </label>
          <button id="activateSelectedTerm" class="btn btn-secondary" type="button">Activate</button>
        </div>
        <div id="termStatus" class="status" role="status" style="margin-bottom:18px;"></div>

        <!-- Create -->
        <div style="border-top:1px solid var(--card-border); padding-top:16px; margin-bottom:6px;">
          <h3 style="margin:0 0 12px; font-size:0.95rem; font-weight:600;">Create a New Semester</h3>
          <form id="termCreateForm">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
              <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
                Label
                <input name="label" type="text" placeholder="e.g. Semester 1 (2026)" style="padding:8px 10px;" required />
              </label>
              <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
                Semester #
                <select name="semester" style="padding:8px 10px;" required>
                  <option value="1">Semester 1</option>
                  <option value="2">Semester 2</option>
                </select>
              </label>
              <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
                Start Date <span class="muted">(optional)</span>
                <input name="start_date" type="date" style="padding:8px 10px;" />
              </label>
              <label style="display:flex; flex-direction:column; gap:4px; font-size:0.83rem;">
                End Date <span class="muted">(optional)</span>
                <input name="end_date" type="date" style="padding:8px 10px;" />
              </label>
              <button class="btn btn-secondary" type="submit">Create</button>
            </div>
          </form>
          <div id="createTermStatus" class="status" role="status" style="margin-top:8px; margin-bottom:18px;"></div>
        </div>

        <!-- All semesters -->
        <div style="border-top:1px solid var(--card-border); padding-top:16px;">
          <h3 style="margin:0 0 12px; font-size:0.95rem; font-weight:600;">All Semesters</h3>
          <div class="table-wrap">
            <table id="termsTable" class="data-table">
              <thead>
                <tr><th>Label</th><th>Sem</th><th>Year</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

      </div>
    </details>

    <!-- Reset Weeks modal (built by JS) -->
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/admin_terms.js?v=20260222c"></script>
  <script src="js/admin_advance.js?v=20260222c"></script>
  <script>
    window.dmportal?.initNavbar?.({});
  </script>
</body>
</html>
