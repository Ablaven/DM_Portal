<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';
require_once __DIR__ . '/php/_system_settings_helpers.php';
require_once __DIR__ . '/php/db_connect.php';

auth_require_page_access('admin_panel.php');
auth_require_roles(['admin']);

// Get current attendance timeout status
$pdo = get_pdo();
$timeoutStatus = dmportal_get_attendance_timeout_status($pdo);

$importStatus = $_GET['import_status'] ?? '';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Panel</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('admin_panel.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Admin Panel</h1>
      <p class="subtitle">Central place for admin-only tools and utilities.</p>
    </header>

    <!-- ── Tab Navigation ──────────────────────────────────────────────────── -->
    <div class="tabs" style="margin-bottom:24px;">
      <button class="tab active" data-tab="database" type="button">💾 Database</button>
      <button class="tab" data-tab="settings" type="button">⚙️ System Settings</button>
      <button class="tab" data-tab="academic" type="button">🗓️ Academic Year</button>
      <button class="tab" data-tab="reports" type="button">📊 Reports & Tracking</button>
    </div>

    <!-- Sections will be shown/hidden based on tab selection via JS -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         DATABASE TOOLS
         ═══════════════════════════════════════════════════════════════════════ -->
    <section class="card" data-section="database">
      <div style="margin-bottom:32px;">
        <h2 style="margin:0 0 8px; font-size:1.4rem;">💾 Database Management</h2>
        <p class="muted" style="margin:0; font-size:0.95rem;">Backup and restore your entire database with SQL dumps.</p>
      </div>

      <!-- Export Card -->
      <div style="display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
        <div style="padding:24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px;">
          <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--accent-rgb),0.2), rgba(var(--accent-rgb),0.1)); border-radius:12px; font-size:1.5rem;">
              📥
            </div>
            <div style="flex:1;">
              <h3 style="margin:0 0 4px; font-size:1.1rem;">Export Database</h3>
              <p class="muted" style="margin:0; font-size:0.85rem;">Download complete SQL backup</p>
            </div>
          </div>
          <a class="btn btn-secondary" href="php/export_database_sql.php" style="width:100%; justify-content:center; display:flex; align-items:center; gap:8px;">
            <span>📥</span> Export SQL
          </a>
        </div>

        <!-- Import Card -->
        <div style="padding:24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px;">
          <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--success-rgb),0.2), rgba(var(--success-rgb),0.1)); border-radius:12px; font-size:1.5rem;">
              📤
            </div>
            <div style="flex:1;">
              <h3 style="margin:0 0 4px; font-size:1.1rem;">Import Database</h3>
              <p class="muted" style="margin:0; font-size:0.85rem;">Restore from SQL backup file</p>
            </div>
          </div>
          
          <form action="php/import_database_sql.php" method="post" enctype="multipart/form-data" style="margin:0;">
            <div class="field" style="margin:0 0 12px;">
              <input id="importSqlFile" name="sql_file" type="file" accept=".sql" required style="width:100%;" />
            </div>
            
            <label class="chk" style="display:flex; align-items:flex-start; gap:8px; margin-bottom:16px; padding:12px; background:rgba(var(--danger-rgb),0.08); border:1px solid rgba(var(--danger-rgb),0.2); border-radius:10px; cursor:pointer;"
              title="Leave unchecked for normal imports. The importer drops all tables and views in the database from .env, then runs your file. Avoids foreign-key errors when old tables referenced recreated ones. Check only if the database is empty or your dump already includes DROP statements.">
              <input type="checkbox" name="skip_drop_before_create" value="1" style="margin-top:2px;" />
              <span style="font-size:0.85rem; line-height:1.5;">
                <strong>Skip full database wipe</strong><br/>
                <span class="muted">Only check if your dump includes DROP statements or database is empty</span>
              </span>
            </label>
            
            <button class="btn" type="submit" style="width:100%; justify-content:center; display:flex; align-items:center; gap:8px; background:linear-gradient(135deg, rgba(var(--success-rgb),0.9), rgba(var(--success-rgb),0.7));">
              <span>📤</span> Upload & Import
            </button>
          </form>
          
          <?php if ($importStatus === 'success') : ?>
            <div class="status success" role="status" style="margin-top:16px;">✓ Import completed successfully!</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Warning Notice -->
      <div style="margin-top:24px; padding:16px 20px; background:rgba(var(--danger-rgb),0.08); border:1px solid rgba(var(--danger-rgb),0.2); border-radius:12px; display:flex; gap:12px; align-items:flex-start;">
        <span style="font-size:1.5rem; flex-shrink:0;">⚠️</span>
        <div>
          <strong style="display:block; margin-bottom:4px; color:var(--danger);">Important</strong>
          <p class="muted" style="margin:0; font-size:0.88rem; line-height:1.6;">
            Importing will <strong>replace all data</strong> in the database. Make sure to export a backup first. This action cannot be undone.
          </p>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SYSTEM SETTINGS
         ═══════════════════════════════════════════════════════════════════════ -->
    <section class="card" style="margin-top:20px;" data-section="settings">
      <div style="margin-bottom:32px;">
        <h2 style="margin:0 0 8px; font-size:1.4rem;">⚙️ System Settings</h2>
        <p class="muted" style="margin:0; font-size:0.95rem;">Configure global system behaviors and access controls.</p>
      </div>

      <!-- Attendance Timeout Setting -->
      <div style="padding:24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px; margin-bottom:20px;">
        <div style="display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap;">
          <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--accent-rgb),0.2), rgba(var(--accent-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
            ⏱️
          </div>
          
          <div style="flex:1; min-width:250px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:12px;">
              <div style="flex:1;">
                <h3 style="margin:0 0 8px; font-size:1.15rem;">Attendance Timeout Enforcement</h3>
                <p class="muted" style="margin:0; font-size:0.9rem; line-height:1.6;">
                  When <strong>disabled</strong>, teachers can take attendance at any time without the 30-minute grace period restriction.
                  When <strong>enabled</strong>, attendance must be taken within 30 minutes after each lecture ends.
                </p>
              </div>
              
              <label class="toggle-switch" style="flex-shrink:0;">
                <input 
                  type="checkbox" 
                  id="attendanceTimeoutToggle"
                  <?php echo $timeoutStatus['disabled'] ? 'checked' : ''; ?>
                />
                <span class="toggle-slider"></span>
                <span class="toggle-label" style="margin-left:10px; font-size:0.95rem; font-weight:600;">
                  <?php echo $timeoutStatus['disabled'] ? 'Disabled' : 'Enabled'; ?>
                </span>
              </label>
            </div>
            
            <div id="attendanceTimeoutStatus" class="status" role="status" style="margin-top:12px;"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Page Access Control ─────────────────────────────────────────────── -->
    <section class="card" style="margin-top:20px;" data-section="settings">
      <div style="padding:24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px;">
        <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:24px;">
          <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--danger-rgb),0.2), rgba(var(--danger-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
            🔒
          </div>
          
          <div style="flex:1;">
            <h3 style="margin:0 0 8px; font-size:1.15rem;">Page Access Control</h3>
            <p class="muted" style="margin:0; font-size:0.9rem; line-height:1.6;">
              Enable or disable specific pages for different user roles. <strong>Admin always has full access.</strong>
            </p>
          </div>
        </div>

        <!-- Control Panel -->
        <div style="padding:20px; background:var(--surface-1); border:1px solid var(--card-border); border-radius:12px; margin-bottom:24px;">
          <div style="display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:16px;">
            <div class="field" style="margin:0;">
              <label for="pageAccessRole" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">👤 User Role</label>
              <select id="pageAccessRole" class="navlink" style="padding:10px 14px; width:100%;">
                <option value="">Select role...</option>
                <option value="teacher">Teacher</option>
                <option value="management">Management</option>
                <option value="student">Student</option>
              </select>
            </div>

            <div class="field" style="margin:0;">
              <label for="pageAccessPage" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📄 Page</label>
              <select id="pageAccessPage" class="navlink" style="padding:10px 14px; width:100%;" disabled>
                <option value="">Select a role first...</option>
              </select>
            </div>

            <div style="display:flex; flex-direction:column; justify-content:flex-end;">
              <label class="toggle-switch" id="pageAccessToggleContainer" style="opacity:0.5; pointer-events:none; padding:10px 0;">
                <input type="checkbox" id="pageAccessToggle" disabled />
                <span class="toggle-slider"></span>
                <span class="toggle-label" style="margin-left:10px; font-size:0.95rem; font-weight:600;" id="pageAccessToggleLabel">
                  Enabled
                </span>
              </label>
            </div>
          </div>
          
          <div id="pageAccessStatus" class="status" role="status"></div>
        </div>

        <!-- Currently Disabled Pages -->
        <div>
          <h4 style="margin:0 0 16px; font-size:1.05rem; display:flex; align-items:center; gap:8px;">
            <span>🚫</span> Currently Disabled Pages
          </h4>
          <div id="disabledPagesList" style="display:grid; gap:12px;">
          <div class="muted" style="font-size:0.9rem; padding:12px; text-align:center;">
            Loading...
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════════
         ACADEMIC YEAR MANAGEMENT
         ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Semester Wizard ─────────────────────────────────────────────────── -->
    <section class="card" id="semesterWizard" style="margin-top:20px;" data-section="academic">

      <!-- Header: always visible, shows where you are -->
      <div style="padding:24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px; margin-bottom:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
          <div style="display:flex; align-items:center; gap:16px; flex:1;">
            <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--accent-rgb),0.2), rgba(var(--accent-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
              🗓️
            </div>
            <div>
              <div class="muted" style="font-size:0.8rem; letter-spacing:.06em; text-transform:uppercase; margin-bottom:6px; font-weight:600;">Semester Management</div>
              <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <strong id="wizardCurrentLabel" style="font-size:1.2rem;">Loading…</strong>
                <span id="wizardNextHint" class="muted" style="font-size:0.9rem;"></span>
              </div>
            </div>
          </div>
          <button id="wizManualBtn" class="btn btn-small btn-secondary" type="button" style="white-space:nowrap; padding:10px 18px;">⚙ Manual Options</button>
        </div>
      </div>

      <!-- Wizard Content Area -->
      <div style="padding:24px; background:var(--surface-1); border:1px solid var(--card-border); border-radius:16px;">

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
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;">Ready to advance - here's what will happen:</p>
        <div id="wizStep2aSummary" style="background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px; padding:16px 18px; margin-bottom:18px; line-height:2;"></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="wizStep2aConfirm" class="btn" type="button">Advance to Semester 2</button>
          <button id="wizStep2aBack" class="btn btn-secondary" type="button">← Back</button>
        </div>
        <div id="wizStep2aStatus" class="status" role="status" style="margin-top:10px;"></div>
      </div>

      <!-- STEP 2b: Sem 2→Year - student rule -->
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
        <p class="muted" style="margin:0 0 14px; font-size:0.95rem;">Ready to advance - here's what will happen:</p>
        <div id="wizStep3bSummary" style="background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px; padding:16px 18px; margin-bottom:18px; line-height:2;"></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="wizStep3bConfirm" class="btn" type="button">Start New Academic Year</button>
          <button id="wizStep3bBack" class="btn btn-secondary" type="button">← Back</button>
        </div>
        <div id="wizStep3bStatus" class="status" role="status" style="margin-top:10px;"></div>
      </div>

    </section>

    <!-- ═══════════════════════════════════════════════════════════════════════
         REPORTS & TRACKING
         ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Attendance Tracking Report ─────────────────────────────────────── -->
    <section class="card" style="margin-top:20px;" data-section="reports">
      <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
        <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--success-rgb),0.2), rgba(var(--success-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
          ✅
        </div>
        <div>
          <h2 style="margin:0 0 6px; font-size:1.3rem;">Attendance Tracking Report</h2>
          <p class="muted" style="margin:0; font-size:0.92rem;">View and export attendance status for a range of weeks.</p>
        </div>
      </div>

      <!-- Filters -->
      <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px; margin-bottom:20px;">
        <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:16px;">
          <div class="field" style="margin:0;">
            <label for="attendanceTrackingTermFilter" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📚 Term/Semester</label>
            <select id="attendanceTrackingTermFilter" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Terms</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="attendanceTrackingFromWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 From Week</label>
            <select id="attendanceTrackingFromWeek" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">Select week…</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="attendanceTrackingToWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 To Week</label>
            <select id="attendanceTrackingToWeek" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">Select week…</option>
            </select>
          </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="loadAttendanceTrackingReport" class="btn btn-secondary" type="button" style="flex:1; min-width:140px;">📊 Load Report</button>
          <button id="exportAttendanceTrackingReport" class="btn" type="button" disabled style="flex:1; min-width:140px; background:linear-gradient(135deg, rgba(var(--success-rgb),0.9), rgba(var(--success-rgb),0.7));">📥 Export Excel</button>
        </div>
      </div>

      <div id="attendanceTrackingStatus" class="status" role="status" style="margin-bottom:12px;"></div>

      <!-- Summary stats -->
      <div id="attendanceTrackingSummary" style="display:none; margin-bottom:20px; padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
        <div style="display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Total Lectures</div>
            <div style="font-size:2rem; font-weight:700;" id="attendanceTrackingTotal">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Attendance Taken</div>
            <div style="font-size:2rem; font-weight:700; color:var(--success);" id="attendanceTrackingTaken">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Missing</div>
            <div style="font-size:2rem; font-weight:700; color:var(--danger);" id="attendanceTrackingMissing">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Canceled</div>
            <div style="font-size:2rem; font-weight:700; color:var(--muted);" id="attendanceTrackingCanceled">0</div>
          </div>
        </div>
      </div>

      <!-- Data table -->
      <div id="attendanceTrackingTableWrap" style="display:none;">
        <div style="margin-bottom:16px; padding:16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px;">
          <div style="display:grid; gap:10px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <select id="attendanceTrackingFilterCourse" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Courses</option>
            </select>
            <select id="attendanceTrackingFilterProfessor" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Professors</option>
            </select>
            <select id="attendanceTrackingFilterStatus" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Status</option>
              <option value="missing">Missing Only</option>
              <option value="taken">Taken Only</option>
              <option value="canceled">Canceled Only</option>
            </select>
          </div>
        </div>

        <div class="table-wrap" style="max-height:600px; overflow:auto;">
          <table class="data-table" id="attendanceTrackingTable">
            <thead>
              <tr>
                <th>Week</th>
                <th>Date</th>
                <th>Day</th>
                <th>Time</th>
                <th>Course</th>
                <th>Professor</th>
                <th>Status</th>
                <th>Attendance</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ── Professor Attendance Tracking ───────────────────────────────────── -->
    <section class="card" style="margin-top:20px;" data-section="reports">
      <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
        <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--accent-rgb),0.2), rgba(var(--accent-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
          🧑‍🏫
        </div>
        <div>
          <h2 style="margin:0 0 6px; font-size:1.3rem;">Professor Attendance Tracking</h2>
          <p class="muted" style="margin:0; font-size:0.92rem;">Track professor attendance based on whether they took attendance for scheduled lectures.</p>
        </div>
      </div>

      <!-- Filters -->
      <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px; margin-bottom:20px;">
        <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:16px;">
          <div class="field" style="margin:0;">
            <label for="professorTrackingTermFilter" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📚 Term/Semester</label>
            <select id="professorTrackingTermFilter" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Terms</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="professorTrackingFromWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 From Week</label>
            <select id="professorTrackingFromWeek" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">Select week…</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="professorTrackingToWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 To Week</label>
            <select id="professorTrackingToWeek" class="navlink" style="padding:10px 12px; width:100%;">
            <option value="">Select week…</option>
          </select>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="loadProfessorTrackingReport" class="btn btn-secondary" type="button" style="flex:1; min-width:140px;">📊 Load Report</button>
          <button id="exportProfessorTrackingReport" class="btn" type="button" disabled style="flex:1; min-width:140px; background:linear-gradient(135deg, rgba(var(--success-rgb),0.9), rgba(var(--success-rgb),0.7));">📥 Export Excel</button>
        </div>
      </div>

      <div id="professorTrackingStatus" class="status" role="status" style="margin-bottom:12px;"></div>

      <!-- Summary stats -->
      <div id="professorTrackingSummary" style="display:none; margin-bottom:20px; padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
        <div style="display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Total Lectures</div>
            <div style="font-size:2rem; font-weight:700;" id="professorTrackingTotalLectures">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Professor Present</div>
            <div style="font-size:2rem; font-weight:700; color:var(--success);" id="professorTrackingPresent">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Professor Absent</div>
            <div style="font-size:2rem; font-weight:700; color:var(--danger);" id="professorTrackingAbsent">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Canceled</div>
            <div style="font-size:2rem; font-weight:700;" id="professorTrackingCanceled">0</div>
          </div>
          <div style="text-align:center;">
            <div class="muted" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; font-weight:600;">Attendance Rate</div>
            <div style="font-size:2rem; font-weight:700;" id="professorTrackingRate">0%</div>
          </div>
        </div>
      </div>

      <!-- Data table -->
      <div id="professorTrackingTableWrap" style="display:none;">
        <div style="margin-bottom:16px; padding:16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px;">
          <div style="display:grid; gap:10px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <select id="professorTrackingFilterCourse" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Courses</option>
            </select>
            <select id="professorTrackingFilterProfessor" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Professors</option>
            </select>
            <select id="professorTrackingFilterStatus" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Status</option>
              <option value="present">Present Only</option>
              <option value="absent">Absent Only</option>
              <option value="canceled">Canceled Only</option>
            </select>
          </div>
        </div>

        <div class="table-wrap" style="max-height:600px; overflow:auto;">
          <table class="data-table" id="professorTrackingTable">
            <thead>
              <tr>
                <th>Week</th>
                <th>Date</th>
                <th>Day</th>
                <th>Time</th>
                <th>Course</th>
                <th>Professor</th>
                <th>Status</th>
                <th>Teacher Attendance</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ── Teacher Schedule Export ─────────────────────────────────────────── -->
    <section class="card" style="margin-top:20px;" data-section="reports">
      <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
        <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--info-rgb),0.2), rgba(var(--info-rgb),0.1)); border-radius:14px; font-size:1.8rem; flex-shrink:0;">
          📊
        </div>
        <div>
          <h2 style="margin:0 0 6px; font-size:1.3rem;">Teacher Schedule Export</h2>
          <p class="muted" style="margin:0; font-size:0.92rem;">Export teacher schedules for a range of weeks by nationality.</p>
        </div>
      </div>

      <!-- Filters -->
      <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px; margin-bottom:20px;">
        <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:16px;">
          <div class="field" style="margin:0;">
            <label for="teacherNationalityFilter" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">🌍 Nationality Filter</label>
            <select id="teacherNationalityFilter" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="All">All Teachers</option>
              <option value="French">French Teachers Only</option>
              <option value="Egyptian">Egyptian Teachers Only</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="teacherScheduleTermFilter" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📚 Term/Semester</label>
            <select id="teacherScheduleTermFilter" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">All Terms</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="teacherScheduleFromWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 From Week</label>
            <select id="teacherScheduleFromWeek" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">Select week…</option>
            </select>
          </div>

          <div class="field" style="margin:0;">
            <label for="teacherScheduleToWeek" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">📅 To Week</label>
            <select id="teacherScheduleToWeek" class="navlink" style="padding:10px 12px; width:100%;">
              <option value="">Select week…</option>
            </select>
          </div>
        </div>

        <button id="exportTeacherSchedule" class="btn" type="button" style="width:100%; background:linear-gradient(135deg, rgba(var(--success-rgb),0.9), rgba(var(--success-rgb),0.7));">📥 Export Excel</button>
      </div>

      <div id="teacherScheduleStatus" class="status" role="status" style="margin-bottom:12px;"></div>
      </div><!-- End wizard content area -->
    </section>

    <!-- ── Manual Options (collapsed) ──────────────────────────────────────── -->
    <details id="manualOptionsPanel" style="margin-top:20px; padding:20px 24px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:16px;" data-section="academic">
      <summary style="cursor:pointer; font-weight:600; padding:12px 0; user-select:none; list-style:none; display:flex; align-items:center; gap:12px; font-size:1.05rem;">
        <span style="width:40px; height:40px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, rgba(var(--accent-rgb),0.15), rgba(var(--accent-rgb),0.08)); border-radius:10px; font-size:1.3rem;">⚙️</span>
        <span style="flex:1;">Manual Options</span>
        <span class="muted" style="font-weight:400; font-size:0.88rem;">(activate, create, or reset semesters)</span>
      </summary>

      <div style="margin-top:20px; padding:20px; background:var(--surface-1); border:1px solid var(--card-border); border-radius:12px;">

        <!-- Activate -->
        <div style="margin-bottom:28px;">
          <h3 style="margin:0 0 8px; font-size:1.05rem; font-weight:600; display:flex; align-items:center; gap:8px;">
            <span>✓</span> Activate a Semester
          </h3>
          <p class="muted" style="margin:0 0 16px; font-size:0.88rem;">Switch to a different academic term</p>
          <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:6px;">
            <div class="field" style="margin:0; flex:1; min-width:160px;">
              <label for="academicYearSelect" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">Academic Year</label>
              <select id="academicYearSelect" class="navlink" style="padding:10px 12px; width:100%;"></select>
            </div>
            <div class="field" style="margin:0; flex:1; min-width:160px;">
              <label for="termSelect" style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">Semester</label>
              <select id="termSelect" class="navlink" style="padding:10px 12px; width:100%;"></select>
            </div>
            <button id="activateSelectedTerm" class="btn btn-secondary" type="button" style="padding:10px 24px;">Activate</button>
          </div>
          <div id="termStatus" class="status" role="status"></div>
        </div>

        <!-- Create -->
        <div style="border-top:1px solid var(--card-border); padding-top:24px; margin-bottom:28px;">
          <h3 style="margin:0 0 8px; font-size:1.05rem; font-weight:600; display:flex; align-items:center; gap:8px;">
            <span>➕</span> Create a New Semester
          </h3>
          <p class="muted" style="margin:0 0 16px; font-size:0.88rem;">The label is auto-generated from the semester number (e.g. "Semester 1" or "Semester 2").</p>
          <form id="termCreateForm">
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
              <div class="field" style="margin:0; min-width:140px;">
                <label style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">Semester #</label>
                <select name="semester" class="navlink" style="padding:10px 12px; width:100%;" required>
                  <option value="1">Semester 1</option>
                  <option value="2">Semester 2</option>
                </select>
              </div>
              <div class="field" style="margin:0; flex:1; min-width:160px;">
                <label style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">Start Date <span class="muted">(optional)</span></label>
                <input name="start_date" type="date" style="padding:10px 12px; width:100%;" />
              </div>
              <div class="field" style="margin:0; flex:1; min-width:160px;">
                <label style="font-size:0.88rem; margin-bottom:6px; font-weight:600;">End Date <span class="muted">(optional)</span></label>
                <input name="end_date" type="date" style="padding:10px 12px; width:100%;" />
              </div>
              <button class="btn btn-secondary" type="submit" style="padding:10px 24px;">Create</button>
            </div>
          </form>
          <div id="createTermStatus" class="status" role="status" style="margin-top:12px;"></div>
        </div>

        <!-- All semesters -->
        <div style="border-top:1px solid var(--card-border); padding-top:24px;">
          <h3 style="margin:0 0 16px; font-size:1.05rem; font-weight:600; display:flex; align-items:center; gap:8px;">
            <span>📋</span> All Semesters
          </h3>
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

  <script src="js/core.js?v=20260912a"></script>
  <script>
    // ── Admin Panel Tabs ────────────────────────────────────────────────────
    (function () {
      "use strict";

      const tabs = document.querySelectorAll('.tabs .tab');
      const sections = document.querySelectorAll('[data-section]');

      // Get saved tab from localStorage or default to 'database'
      let activeTab = localStorage.getItem('adminPanelActiveTab') || 'database';

      // Function to show sections for a given tab
      function showTab(tabName) {
        // Update tab buttons
        tabs.forEach(tab => {
          if (tab.dataset.tab === tabName) {
            tab.classList.add('active');
          } else {
            tab.classList.remove('active');
          }
        });

        // Show/hide sections
        sections.forEach(section => {
          if (section.dataset.section === tabName) {
            section.style.display = '';
          } else {
            section.style.display = 'none';
          }
        });

        // Save to localStorage
        localStorage.setItem('adminPanelActiveTab', tabName);
        activeTab = tabName;
      }

      // Initialize - show the saved/default tab
      showTab(activeTab);

      // Tab click handlers
      tabs.forEach(tab => {
        tab.addEventListener('click', function() {
          showTab(this.dataset.tab);
        });
      });
    })();

    // ── Attendance Timeout Toggle ──────────────────────────────────────────
    (function () {
      "use strict";

      const toggle = document.getElementById("attendanceTimeoutToggle");
      const statusDiv = document.getElementById("attendanceTimeoutStatus");
      const labelSpan = toggle?.closest(".toggle-switch")?.querySelector(".toggle-label");

      if (!toggle) return;

      toggle.addEventListener("change", async function () {
        const isChecked = this.checked;
        
        // Disable toggle during request
        toggle.disabled = true;
        statusDiv.className = "status info";
        statusDiv.textContent = "Updating...";
        statusDiv.style.display = "block";

        try {
          const response = await fetch("php/toggle_attendance_timeout.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
          });

          const data = await response.json();

          if (data.success) {
            statusDiv.className = "status success";
            statusDiv.textContent = data.message || "Setting updated successfully.";
            
            // Update label text
            if (labelSpan) {
              labelSpan.textContent = data.disabled ? "Disabled" : "Enabled";
            }
            
            // Hide status after 5 seconds
            setTimeout(() => {
              statusDiv.style.display = "none";
            }, 5000);
          } else {
            // Revert toggle on error
            toggle.checked = !isChecked;
            statusDiv.className = "status error";
            statusDiv.textContent = data.error || "Failed to update setting.";
          }
        } catch (error) {
          // Revert toggle on error
          toggle.checked = !isChecked;
          statusDiv.className = "status error";
          statusDiv.textContent = "Network error. Please try again.";
          console.error("Toggle error:", error);
        } finally {
          toggle.disabled = false;
        }
      });
    })();

    // ── Page Access Control ────────────────────────────────────────────────
    (function () {
      "use strict";

      const roleSelect = document.getElementById("pageAccessRole");
      const pageSelect = document.getElementById("pageAccessPage");
      const toggle = document.getElementById("pageAccessToggle");
      const toggleContainer = document.getElementById("pageAccessToggleContainer");
      const toggleLabel = document.getElementById("pageAccessToggleLabel");
      const statusDiv = document.getElementById("pageAccessStatus");
      const disabledList = document.getElementById("disabledPagesList");

      let availablePages = {};
      let disabledPages = {};
      let currentRole = "";
      let currentPage = "";

      // Load initial data
      async function loadDisabledPages() {
        try {
          const response = await fetch("php/get_disabled_pages.php");
          const data = await response.json();

          if (data.success) {
            availablePages = data.data.available_pages;
            disabledPages = data.data.disabled_pages;
            renderDisabledList();
          }
        } catch (error) {
          console.error("Failed to load disabled pages:", error);
        }
      }

      // Render disabled pages list
      function renderDisabledList() {
        if (!disabledList) return;

        let html = "";
        let hasDisabled = false;

        for (const role in disabledPages) {
          if (Object.keys(disabledPages[role]).length > 0) {
            hasDisabled = true;
            const roleLabel = role.charAt(0).toUpperCase() + role.slice(1);
            html += `<div style="padding:12px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px;">`;
            html += `<div style="font-weight:600; font-size:0.9rem; margin-bottom:8px; color:var(--danger);">🔒 ${roleLabel}</div>`;
            html += `<div style="display:flex; flex-wrap:wrap; gap:8px;">`;
            
            for (const page in disabledPages[role]) {
              const pageName = disabledPages[role][page];
              html += `<span style="padding:6px 12px; background:rgba(var(--danger-rgb),0.15); color:var(--danger); border:1px solid rgba(var(--danger-rgb),0.3); border-radius:999px; font-size:0.85rem; font-weight:600;">${pageName}</span>`;
            }
            
            html += `</div></div>`;
          }
        }

        if (!hasDisabled) {
          html = `<div class="muted" style="font-size:0.9rem; padding:12px; text-align:center; background:var(--surface-2); border:1px solid var(--card-border); border-radius:10px;">
            ✓ All pages are currently enabled for all roles.
          </div>`;
        }

        disabledList.innerHTML = html;
      }

      // Update page dropdown when role changes
      roleSelect?.addEventListener("change", function () {
        currentRole = this.value;
        currentPage = "";
        
        if (!currentRole) {
          pageSelect.innerHTML = '<option value="">Select a role first...</option>';
          pageSelect.disabled = true;
          toggleContainer.style.opacity = "0.5";
          toggleContainer.style.pointerEvents = "none";
          toggle.disabled = true;
          return;
        }

        const pages = availablePages[currentRole] || {};
        let options = '<option value="">Select a page...</option>';
        
        for (const page in pages) {
          options += `<option value="${page}">${pages[page]}</option>`;
        }
        
        pageSelect.innerHTML = options;
        pageSelect.disabled = false;
        toggleContainer.style.opacity = "0.5";
        toggleContainer.style.pointerEvents = "none";
        toggle.disabled = true;
        toggleLabel.textContent = "Enabled";
        toggle.checked = false;
        statusDiv.style.display = "none";
      });

      // Update toggle when page changes
      pageSelect?.addEventListener("change", function () {
        currentPage = this.value;
        
        if (!currentPage || !currentRole) {
          toggleContainer.style.opacity = "0.5";
          toggleContainer.style.pointerEvents = "none";
          toggle.disabled = true;
          return;
        }

        // Check if this page is currently disabled
        const isDisabled = disabledPages[currentRole] && disabledPages[currentRole][currentPage];
        
        toggle.checked = isDisabled;
        toggleLabel.textContent = isDisabled ? "Disabled" : "Enabled";
        toggleContainer.style.opacity = "1";
        toggleContainer.style.pointerEvents = "auto";
        toggle.disabled = false;
        statusDiv.style.display = "none";
      });

      // Handle toggle change
      toggle?.addEventListener("change", async function () {
        if (!currentPage || !currentRole) return;

        const isChecked = this.checked;
        
        toggle.disabled = true;
        toggleContainer.style.opacity = "0.7";
        statusDiv.className = "status info";
        statusDiv.textContent = "Updating...";
        statusDiv.style.display = "block";

        try {
          const formData = new URLSearchParams();
          formData.append("page", currentPage);
          formData.append("role", currentRole);

          const response = await fetch("php/toggle_page_access.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData,
          });

          const data = await response.json();

          if (data.success) {
            statusDiv.className = "status success";
            statusDiv.textContent = data.message || "Setting updated successfully.";
            
            toggleLabel.textContent = data.disabled ? "Disabled" : "Enabled";
            
            // Update local disabled pages cache
            if (data.disabled) {
              if (!disabledPages[currentRole]) disabledPages[currentRole] = {};
              disabledPages[currentRole][currentPage] = data.page_name;
            } else {
              if (disabledPages[currentRole]) {
                delete disabledPages[currentRole][currentPage];
              }
            }
            
            renderDisabledList();
            
            setTimeout(() => {
              statusDiv.style.display = "none";
            }, 5000);
          } else {
            toggle.checked = !isChecked;
            statusDiv.className = "status error";
            statusDiv.textContent = data.error || "Failed to update page access.";
          }
        } catch (error) {
          toggle.checked = !isChecked;
          statusDiv.className = "status error";
          statusDiv.textContent = "Network error. Please try again.";
          console.error("Toggle error:", error);
        } finally {
          toggle.disabled = false;
          toggleContainer.style.opacity = "1";
        }
      });

      // Initialize
      loadDisabledPages();
    })();

    // ── Upload Confirmation Modal ──────────────────────────────────────────
    (function () {
      "use strict";

      function getOrCreateUploadConfirmModal() {
        const existing = document.getElementById("uploadConfirmModal");
        if (existing) return existing;

        // Backdrop
        const modal = document.createElement("div");
        modal.id = "uploadConfirmModal";
        modal.className = "modal";
        modal.setAttribute("aria-hidden", "true");

        const backdrop = document.createElement("div");
        backdrop.className = "modal-backdrop";
        backdrop.dataset.close = "1";

        // Card
        const card = document.createElement("div");
        card.className = "modal-card";
        card.setAttribute("role", "dialog");
        card.setAttribute("aria-modal", "true");
        card.setAttribute("aria-labelledby", "uploadConfirmTitle");
                // Header
        const header = document.createElement("div");
        header.className = "modal-header";

        const title = document.createElement("h3");
        title.id = "uploadConfirmTitle";
        title.textContent = "Confirm Database Import";

        header.appendChild(title);

        // Body
        const body = document.createElement("div");
        body.className = "modal-body";

        const msg = document.createElement("p");
        msg.className = "muted";
        msg.style.margin = "0";
        msg.textContent = "This will replace the current database with the contents of the uploaded SQL file. This action cannot be undone. Are you sure you want to continue?";

        body.appendChild(msg);

        // Actions
        const actions = document.createElement("div");
        actions.className = "modal-actions";

        const cancelBtn = document.createElement("button");
        cancelBtn.className = "btn btn-secondary";
        cancelBtn.type = "button";
        cancelBtn.textContent = "Cancel";
        cancelBtn.dataset.close = "1";

        const confirmBtn = document.createElement("button");
        confirmBtn.id = "uploadConfirmBtn";
        confirmBtn.className = "btn btn-danger";
        confirmBtn.type = "button";
        confirmBtn.textContent = "Yes, Import";

        actions.appendChild(cancelBtn);
        actions.appendChild(confirmBtn);

        card.appendChild(header);
        card.appendChild(body);
        card.appendChild(actions);
        modal.appendChild(backdrop);
        modal.appendChild(card);
        document.body.appendChild(modal);

        // Close on backdrop / close buttons
        modal.addEventListener("click", (e) => {
          if (e.target.dataset.close === "1") closeUploadModal();
        });

        return modal;
      }

      function openUploadModal() {
        const modal = getOrCreateUploadConfirmModal();
        modal.setAttribute("aria-hidden", "false");
        modal.classList.add("open");
      }

      function closeUploadModal() {
        const modal = document.getElementById("uploadConfirmModal");
        if (!modal) return;
        modal.setAttribute("aria-hidden", "true");
        modal.classList.remove("open");
      }

      document.addEventListener("DOMContentLoaded", () => {
        const form = document.querySelector("form[action='php/import_database_sql.php']");
        if (!form) return;

        form.addEventListener("submit", (e) => {
          const fileInput = document.getElementById("importSqlFile");
          if (!fileInput || !fileInput.files.length) return;

          e.preventDefault();
          openUploadModal();

          const modal = getOrCreateUploadConfirmModal();
          const confirmBtn = modal.querySelector("#uploadConfirmBtn");

          const handler = () => {
            confirmBtn.removeEventListener("click", handler);
            closeUploadModal();
            form.submit();
          };

          // Remove any stale handler before adding a new one
          confirmBtn.replaceWith(confirmBtn.cloneNode(true));
          modal.querySelector("#uploadConfirmBtn").addEventListener("click", handler);
        });
      });
    })();
  </script>
  <script src="js/navbar.js?v=20260912a"></script>
  <script src="js/admin_terms.js?v=20260912a"></script>
  <script src="js/admin_advance.js?v=20260912a"></script>
  <script src="js/admin_attendance_tracking.js?v=20260922e"></script>
  <script src="js/admin_professor_tracking.js?v=20261006a"></script>
  <script src="js/admin_teacher_schedule_export.js?v=20261010a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    
    // Initialize professor tracking when DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        window.dmportal?.initProfessorTracking?.();
        window.dmportal?.initTeacherScheduleExport?.();
      });
    } else {
      window.dmportal?.initProfessorTracking?.();
      window.dmportal?.initTeacherScheduleExport?.();
    }
  </script>
</body>
</html>
