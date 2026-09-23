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

  <main class="container container-top course-dashboard">
    <header class="page-header">
      <h1>Hours Report</h1>
      <p class="subtitle">Hours per doctor per subject: allocated vs done vs remaining, plus totals.</p>
    </header>

    <section class="card">
      <div class="card-header" style="margin-bottom:12px;">
        <div>
          <h2>Details</h2>
        </div>
        <div class="filter-bar report-filters-grid" style="flex-wrap:wrap;">
          <div class="field">
            <label for="hoursReportYearFilter">Academic Year</label>
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
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>
          <?php if (!$isTeacher) : ?>
          <div class="field">
            <label for="hoursReportDoctorFilter">Professor</label>
            <select id="hoursReportDoctorFilter" class="navlink">
              <option value="">Select professor…</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="page-actions">
            <button id="hoursReportRefresh" class="btn btn-secondary" type="button">Refresh</button>
            <?php if (!$isTeacher) : ?>
            <button id="exportHoursReportSummaryXls" class="btn btn-secondary" type="button">Export Professors Totals</button>
            <?php endif; ?>
            <button id="exportHoursReportDetailXls" class="btn btn-secondary" type="button">Export Professor Detail</button>
            <button id="exportHoursReportCustomXls" class="btn btn-secondary" type="button">Custom Export</button>
          </div>
        </div>
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
