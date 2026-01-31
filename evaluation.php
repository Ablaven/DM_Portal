<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('evaluation.php');
auth_require_login();
auth_require_roles(['admin','management','teacher']);

$u = auth_current_user();
$role = (string)($u['role'] ?? '');
$canConfigure = in_array($role, ['admin', 'management'], true);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Evaluation</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="students-view">
  <?php render_portal_navbar('evaluation.php'); ?>

  <main class="container container-top eval-page">
    <header class="page-header">
      <h1>Evaluation</h1>
      <p class="subtitle">Configure grading parameters and enter grades per student.</p>
    </header>

    <div id="evaluationAlert" class="alert" role="alert" hidden></div>

    <section class="card">
      <div class="schedule-header" style="align-items:flex-end;">
        <div class="controls" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
          <div class="field" style="min-width:180px;">
            <label for="evaluationYearFilter">Year</label>
            <select id="evaluationYearFilter">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field" style="min-width:160px;">
            <label for="evaluationSemesterFilter">Semester</label>
            <select id="evaluationSemesterFilter">
              <option value="">All</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>
          <div class="field" style="min-width:280px;">
            <label for="evaluationCourseSelect">Course</label>
            <select id="evaluationCourseSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
          <button id="evaluationRefresh" class="btn btn-secondary btn-small" type="button">Refresh</button>
          <button id="exportEvaluationSummary" class="btn btn-secondary btn-small" type="button">Export Final Grades</button>
          <button id="exportEvaluationGrades" class="btn btn-secondary btn-small" type="button">Export Detailed Grades</button>
          <div id="evaluationStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <nav class="tabs" aria-label="Evaluation tabs" style="margin-top:10px;">
        <?php if ($canConfigure) { ?>
          <button class="tab active" type="button" data-tab="config">Configuration</button>
          <button class="tab" type="button" data-tab="grading">Grading</button>
        <?php } else { ?>
          <button class="tab active" type="button" data-tab="grading">Grading</button>
        <?php } ?>
      </nav>

      <div class="tab-panels">
        <?php if ($canConfigure) { ?>
          <section class="tab-panel" data-tab-panel="config">
            <div class="panel-title-row" style="margin:14px 0 8px;">
              <h2 style="margin:0;">Parameters</h2>
              <button id="saveEvaluationConfig" class="btn btn-small" type="button">Save Configuration</button>
            </div>

            <div class="muted" style="margin-bottom:10px;">Add items per category. Total marks across all items must equal 100.</div>
            <div id="evaluationConfigTotal" class="muted" style="margin-bottom:10px;"></div>

            <div class="schedule-wrap" style="overflow:auto;">
              <table class="schedule-grid" aria-label="Evaluation parameters">
                <thead>
                  <tr>
                    <th style="width:200px;">Category</th>
                    <th style="width:260px;">Item Name</th>
                    <th style="width:160px;" class="col-number">Mark</th>
                    <th style="width:180px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="evaluationConfigBody"></tbody>
              </table>
            </div>

            <div class="legend" style="margin-top:10px;">
              <button id="addEvaluationItem" class="btn btn-secondary btn-small" type="button">Add Item</button>
              <span class="muted" style="margin-left:12px;">Attendance can be included to auto-calculate from attendance records.</span>
            </div>

            <div id="evaluationConfigStatus" class="status" role="status" aria-live="polite"></div>
          </section>
        <?php } ?>

        <section class="tab-panel" data-tab-panel="grading" <?php echo $canConfigure ? 'hidden' : ''; ?>>
          <div class="panel-title-row" style="margin:14px 0 8px;">
            <h2 style="margin:0;">Student Grades</h2>
            <button id="saveEvaluationGrades" class="btn btn-small" type="button">Save Grades</button>
          </div>

          <div class="muted" style="margin-bottom:10px;">Attendance is calculated automatically from the Attendance page. Each grade must be between 0 and the assigned mark.</div>

          <div class="field" style="max-width:320px; margin-bottom:12px;">
            <label for="evaluationStudentSearch">Search</label>
            <input id="evaluationStudentSearch" type="text" placeholder="Type a student name…" />
          </div>

          <div class="schedule-wrap" style="max-height:60vh; overflow:auto;">
            <table class="schedule-grid eval-grades-table" aria-label="Evaluation grades list">
              <thead id="evaluationGradesHead"></thead>
              <tbody id="evaluationGradesBody"></tbody>
            </table>
          </div>

          <div id="evaluationGradesStatus" class="status" role="status" aria-live="polite"></div>
        </section>
      </div>
    </section>

    <div id="evaluationCategoryModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="evaluationCategoryTitle" style="width: min(520px, 92vw);">
        <div class="modal-header">
          <h3 id="evaluationCategoryTitle">Add Category</h3>
          <button class="btn btn-secondary btn-small" type="button" data-close="1">Close</button>
        </div>
        <div class="modal-body">
          <div class="muted">Enter a new category name.</div>
          <div class="field" style="margin-top:12px;">
            <label for="evaluationCategoryName">Category Name</label>
            <input id="evaluationCategoryName" type="text" placeholder="e.g. Practical" />
          </div>
          <div class="modal-actions" style="justify-content:flex-start;">
            <button id="evaluationCategorySave" class="btn btn-small btn-primary" type="button">Save Category</button>
          </div>
        </div>
      </div>
    </div>

    <div id="evaluationSplitModal" class="modal" aria-hidden="true">
      <div class="modal-backdrop" data-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="evaluationSplitTitle" style="width: min(520px, 92vw);">
        <div class="modal-header">
          <h3 id="evaluationSplitTitle">Split Item</h3>
          <button class="btn btn-secondary btn-small" type="button" data-close="1">Close</button>
        </div>
        <div class="modal-body">
          <div class="muted" id="evaluationSplitMeta">Choose how many items to split into.</div>
          <div class="field" style="margin-top:12px;">
            <label for="evaluationSplitCount">Number of splits</label>
            <input id="evaluationSplitCount" type="number" min="2" max="10" step="1" value="2" />
          </div>
          <div class="modal-actions" style="justify-content:flex-start;">
            <button id="evaluationSplitConfirm" class="btn btn-small btn-primary" type="button">Split</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/evaluation.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initEvaluationPage?.({ canConfigure: <?php echo $canConfigure ? 'true' : 'false'; ?> });
  </script>
</body>
</html>
