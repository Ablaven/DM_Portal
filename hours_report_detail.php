<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('hours_report_detail.php');
auth_require_roles(['admin', 'teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$isTeacher = $role === 'teacher';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hours Report</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('hours_report_detail.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <div>
        <h1>Hours Report</h1>
        <p class="subtitle">Track allocated, assigned, and completed teaching hours per professor and course with detailed breakdowns.</p>
      </div>
    </header>

    <!-- Summary Stats -->
    <section class="card" style="margin-bottom:20px;">
      <div style="padding:14px 16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:8px;">
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Professors</div>
            <div style="font-size:1.5rem; font-weight:700;" id="hoursReportTotalDoctors">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total Courses</div>
            <div style="font-size:1.5rem; font-weight:700;" id="hoursReportTotalCourses">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Allocated Hours</div>
            <div style="font-size:1.5rem; font-weight:700;" id="hoursReportTotalAllocated">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Assigned Hours</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="hoursReportTotalAssigned">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Done Hours</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--success);" id="hoursReportTotalDone">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Completion Rate</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="hoursReportCompletionRate">-</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters & Export -->
    <section class="card" style="margin-bottom:20px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
        <div class="report-filters-grid" style="flex:1; min-width:280px;">
          <div class="field">
            <label for="hoursReportYearFilter">Year</label>
            <select id="hoursReportYearFilter" class="navlink">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field">
            <label for="hoursReportSemesterFilter">Semester</label>
            <select id="hoursReportSemesterFilter" class="navlink">
              <option value="">All</option>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
          <?php if (!$isTeacher) : ?>
          <div class="field">
            <label for="hoursReportDoctorFilter">Professor</label>
            <select id="hoursReportDoctorFilter" class="navlink">
              <option value="">All Professors</option>
            </select>
          </div>
          <?php endif; ?>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
          <button id="hoursReportRefresh" class="btn btn-secondary" type="button">Refresh</button>
          <div style="position:relative;">
            <button id="hoursReportExportBtn" class="btn btn-secondary" type="button">Export ▼</button>
            <div id="hoursReportExportMenu" style="display:none; position:absolute; top:calc(100% + 4px); right:0; min-width:220px; background:rgba(255,255,255,0.08); backdrop-filter:blur(48px); border:1px solid rgba(255,255,255,0.12); border-radius:10px; padding:6px; z-index:100; box-shadow:0 8px 32px rgba(0,0,0,0.4);">
              <?php if (!$isTeacher) : ?>
              <button id="exportHoursReportSummaryXls" style="display:block; width:100%; text-align:left; padding:10px 12px; background:transparent; color:inherit; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; transition:background 0.15s ease;">📊 Professors Totals</button>
              <?php endif; ?>
              <button id="exportHoursReportDetailXls" style="display:block; width:100%; text-align:left; padding:10px 12px; background:transparent; color:inherit; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; transition:background 0.15s ease;">📋 Professor Detail</button>
              <button id="exportHoursReportCustomXls" style="display:block; width:100%; text-align:left; padding:10px 12px; background:transparent; color:inherit; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; transition:background 0.15s ease;">📈 Custom Export</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Details -->
    <section class="card">
      <div style="margin-bottom:16px;">
        <h2 style="margin:0 0 6px;">Professor Hours Breakdown</h2>
        <p class="muted" style="margin:0; font-size:0.9rem;">Hours per professor per subject: allocated vs assigned vs done vs remaining.</p>
      </div>

      <div id="hoursReportStatus" class="status" role="status" aria-live="polite"></div>

      <div id="hoursReportRoot" class="course-progress-list" aria-live="polite"></div>
    </section>
  </main>

  <!-- Custom export modal -->
  <div id="hoursReportCustomExportModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="hoursReportCustomExportTitle" style="width:min(760px, 94vw);">
      <div class="modal-header">
        <h3 id="hoursReportCustomExportTitle">Custom Export</h3>
        <button class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
      </div>
      <div class="modal-body">
        <div class="muted mb-12">Select professors and year/semester combinations, then export one workbook (one sheet per professor).</div>

        <?php if (!$isTeacher) : ?>
        <div class="field">
          <label>Doctors</label>
          <div class="filter-bar" style="justify-content:flex-start; gap:10px; padding:0; margin-top:6px;">
            <input id="hoursReportCustomDoctorSearch" class="navlink" type="text" placeholder="Search doctors…" style="min-width:220px;" />
            <button id="hoursReportCustomDoctorsSelectAll" class="btn btn-secondary btn-small" type="button">Select all</button>
            <button id="hoursReportCustomDoctorsClear" class="btn btn-secondary btn-small" type="button">Clear</button>
          </div>
          <div id="hoursReportCustomDoctors" class="grid-2 custom-export-list"></div>
          <small class="hint">Pick one or more professors.</small>
        </div>
        <?php endif; ?>

        <div class="field">
          <label>Year / Semester</label>
          <div class="filter-bar" style="justify-content:flex-start; gap:10px; padding:0; margin-top:6px;">
            <button id="hoursReportCustomYearSemSelectAll" class="btn btn-secondary btn-small" type="button">Select all</button>
            <button id="hoursReportCustomYearSemClear" class="btn btn-secondary btn-small" type="button">Clear</button>
          </div>
          <div id="hoursReportCustomYearSem" class="grid-2 custom-export-list">
            <label class="chk"><input type="checkbox" value="1-1" /> Year 1 / Sem 1</label>
            <label class="chk"><input type="checkbox" value="1-2" /> Year 1 / Sem 2</label>
            <label class="chk"><input type="checkbox" value="2-1" /> Year 2 / Sem 1</label>
            <label class="chk"><input type="checkbox" value="2-2" /> Year 2 / Sem 2</label>
            <label class="chk"><input type="checkbox" value="3-1" /> Year 3 / Sem 1</label>
            <label class="chk"><input type="checkbox" value="3-2" /> Year 3 / Sem 2</label>
          </div>
          <small class="hint">You can export multiple year/semester combinations in one file.</small>
        </div>

        <div id="hoursReportCustomExportStatus" class="status" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-secondary" type="button" data-close="1">Cancel</button>
        <button id="hoursReportCustomExportRun" class="btn" type="button">Export</button>
      </div>
    </div>
  </div>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260228g"></script>
  <script src="js/hours_report.js?v=20260528a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initHoursReportPage?.({
      isTeacher: <?php echo $isTeacher ? 'true' : 'false'; ?>,
      teacherDoctorId: <?php echo $isTeacher ? (int)($u['doctor_id'] ?? 0) : 0; ?>,
    });
  </script>
</body>
</html>
