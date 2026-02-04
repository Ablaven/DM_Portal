<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

// Admin-only scheduling builder.
auth_require_page_access('schedule_builder.php');
auth_require_roles(['admin','management']);

// Simple entry point for XAMPP: http://localhost/<folder>/
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor Schedule Builder</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body class="dashboard">
  <?php render_portal_navbar('schedule_builder.php'); ?>

  <div class="layout layout-single">
    <main class="main">
      <header class="main-header">
        <div>
          <h2>Build Doctor Schedules</h2>
          <p class="muted">Pick a doctor, then click a slot to assign/change/remove.</p>
        </div>
        <div class="main-actions">
          <div class="weekbar">
            <label class="muted" for="weekSelect" style="font-size:0.9rem;">Week</label>
            <select id="weekSelect" class="navlink" style="padding:8px 10px;">
              <option value="">Loading…</option>
            </select>
            <input id="weekStartDate" class="navlink" style="padding:8px 10px;" type="date" />
            <button id="startWeekBtn" class="btn btn-secondary btn-small" type="button">Start</button>
            <button id="stopWeekBtn" class="btn btn-secondary btn-small" type="button">Stop</button>
          </div>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button id="exportDoctorXls" class="btn btn-secondary" type="button">Export Doctor (.xlsx)</button>
            <a id="exportDoctorEmail" class="icon-btn" href="" target="_blank" rel="noopener" title="Email doctor schedule" aria-label="Email doctor schedule" aria-disabled="true">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></svg>
            </a>
            <a id="exportDoctorWhatsApp" class="icon-btn" href="" target="_blank" rel="noopener" title="WhatsApp doctor schedule" aria-label="WhatsApp doctor schedule" aria-disabled="true">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20.5 3.5A11 11 0 0 0 2.9 17.8L2 22l4.3-.9A11 11 0 0 0 20.5 3.5Zm-8.9 17a9 9 0 0 1-4.6-1.2l-.3-.2-2.6.6.6-2.5-.2-.3A9 9 0 1 1 11.6 20.5Zm5-6.4c-.3-.2-1.6-.8-1.9-.9s-.5-.1-.7.2-.8.9-1.1.4-.4.3-.2.1a7.4 7.4 0 0 1-2.2-1.4 8.2 8.2 0 0 1-1.5-1.9c-.2-.4 0-.6.2-.8l.4-.5c.1-.2.2-.4.3-.5.1-.2 0-.4 0-.6s-.7-1.7-1-2.3c-.3-.6-.6-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.1-1.2 2.8 1.2 3.3 1.4 3.5c.2.2 2.3 3.5 5.6 4.9.8.3 1.4.5 1.9.6.8.3 1.6.2 2.2.1.7-.1 2.1-.9 2.4-1.7.3-.8.3-1.5.2-1.7-.1-.2-.3-.3-.6-.5Z"/></svg>
            </a>
          </div>
          <button id="exportAllDoctorsXls" class="btn btn-secondary" type="button">Export All Doctors (.xlsx)</button>
          <button id="refreshSchedule" class="btn btn-secondary" type="button">Refresh</button>
        </div>
      </header>

      <div class="panel" style="margin-bottom:12px; padding:12px 16px;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
          <div class="field" style="margin:0; min-width:220px;">
            <label class="muted" for="doctorSelect" style="font-size:0.85rem;">Doctor</label>
            <select id="doctorSelect" class="navlink" style="padding:8px 10px;">
              <option value="">Loading doctors…</option>
            </select>
          </div>
          <div class="field" style="margin:0; min-width:140px;">
            <label class="muted" style="font-size:0.85rem;" for="builderYearFilterMain">Academic Year</label>
            <select id="builderYearFilterMain" class="navlink" style="padding:7px 10px;">
              <option value="">All Years</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>
          <div class="field" style="margin:0; min-width:140px;">
            <label class="muted" style="font-size:0.85rem;" for="builderSemesterFilterMain">Semester</label>
            <select id="builderSemesterFilterMain" class="navlink" style="padding:7px 10px;">
              <option value="">All Sem</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>
          <p class="muted" style="margin:0;">Remaining hours are computed automatically from Total Hours (each scheduled slot = 1.5h).</p>
        </div>
      </div>

      <section class="panel">
        <div class="schedule-header" style="margin-bottom: 12px;">
          <div>
            <div class="muted">Doctor unavailability (blocks specific dates/times):</div>
            <div class="muted" style="font-size:0.85rem; margin-top:4px;">Use this for sick leave / conferences. It will prevent scheduling into blocked slots.</div>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; justify-content:flex-end;">
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="unavailStart">Start</label>
              <input id="unavailStart" class="navlink" style="padding:8px 10px;" type="datetime-local" />
            </div>
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="unavailEnd">End</label>
              <input id="unavailEnd" class="navlink" style="padding:8px 10px;" type="datetime-local" />
            </div>
            <div class="field" style="margin:0;">
              <label class="muted" style="font-size:0.85rem;" for="unavailReason">Reason</label>
              <input id="unavailReason" class="navlink" style="padding:8px 10px;" type="text" placeholder="optional" />
            </div>
            <button id="addUnavailBtn" class="btn btn-secondary btn-small" type="button">Add</button>
          </div>
        </div>

        <div id="unavailList" class="courses-list" style="margin-top:10px;">
          <div class="muted">No data.</div>
        </div>
        <div id="unavailStatus" class="status" role="status" aria-live="polite"></div>
      </section>

      <section class="panel">
        <div class="schedule-header" style="margin-bottom: 12px;">
          <div class="muted">Cancel a day for the selected doctor (this week):</div>
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
            <select id="cancelDaySelect" class="navlink" style="padding:8px 10px;">
              <option value="Sun">Sunday</option>
              <option value="Mon">Monday</option>
              <option value="Tue">Tuesday</option>
              <option value="Wed">Wednesday</option>
              <option value="Thu">Thursday</option>
            </select>
            <input id="cancelReason" class="navlink" style="padding:8px 10px;" type="text" placeholder="Reason (optional)" />
            <button id="cancelDayBtn" class="btn btn-secondary btn-small" type="button">Cancel Day</button>
            <button id="uncancelDayBtn" class="btn btn-secondary btn-small" type="button">Undo</button>
          </div>
        </div>

        <div id="cancelStatus" class="status" role="status" aria-live="polite"></div>
      </section>

      <section class="panel">
        <div class="schedule-header">
          <div id="scheduleMetaHint" class="muted">Week starts Sunday • Each slot = 1 hour 30 minutes</div>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
            <div id="scheduleStatus" class="status" role="status" aria-live="polite"></div>
          </div>
        </div>

        <div class="schedule-wrap">
          <table class="schedule-grid" aria-label="Weekly schedule grid">
            <thead>
              <tr>
                <th class="corner">Time</th>
                <th>Sun</th>
                <th>Mon</th>
                <th>Tue</th>
                <th>Wed</th>
                <th>Thu</th>
              </tr>
            </thead>
            <tbody id="scheduleBody"></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Simple modal for slot assignment -->
  <div id="slotModal" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="slotModalTitle">
      <div class="modal-header">
        <h3 id="slotModalTitle">Edit Slot</h3>
        <button id="closeModal" class="btn btn-small btn-secondary" type="button" data-close="1">Close</button>
      </div>

      <div class="modal-body">
        <div class="field">
          <label>Slot</label>
          <input id="modalSlotLabel" type="text" readonly />
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="modal_course_code">Course Code</label>
            <select id="modal_course_code">
              <option value="">Select a code</option>
            </select>
          </div>

          <div class="field">
            <label for="modal_course_name">Course Name</label>
            <select id="modal_course_name">
              <option value="">Select a course</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="modal_room_code">Room / Lab</label>
          <input id="modal_room_code" type="text" placeholder="e.g. Lab A • 101" maxlength="50" />
        </div>
        <small class="hint">Enter the room or lab name/code (required).</small>

        <div class="field" style="margin-top:10px;">
          <label style="display:flex; gap:10px; align-items:center;">
            <input id="modal_counts_towards_hours" type="checkbox" checked />
            <span>Counts towards hours</span>
          </label>
          <small class="hint">Hours are only calculated/subtracted when checked.</small>
        </div>

        <div class="field" style="margin-top:10px;">
          <label for="modal_extra_minutes">Extra time (optional)</label>
          <select id="modal_extra_minutes">
            <option value="0">No extra time</option>
            <option value="15">+15 minutes</option>
            <option value="30">+30 minutes</option>
            <option value="45">+45 minutes</option>
          </select>
          <small class="hint">Adds extra minutes to the deducted course hours (slot base stays 1h 30m).</small>
        </div>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.08); margin:12px 0;" />

        <div class="field">
          <label for="modal_slot_cancel_reason">Slot cancellation reason (optional)</label>
          <input id="modal_slot_cancel_reason" type="text" placeholder="optional" />
          <small class="hint">Canceling a slot blocks scheduling in it (without canceling the whole day).</small>
        </div>

        <div id="modalConflict" class="status" role="status" aria-live="polite"></div>
        <div id="modalStatus" class="status" role="status" aria-live="polite"></div>
      </div>

      <div class="modal-actions" style="flex-wrap:wrap;">
        <button id="modalSave" class="btn" type="button">Save</button>
        <button id="modalRemove" class="btn btn-secondary" type="button">Remove</button>
        <button id="modalCancelSlot" class="btn btn-secondary" type="button">Cancel Slot</button>
        <button id="modalUncancelSlot" class="btn btn-secondary" type="button">Undo Slot Cancel</button>
        <button class="btn btn-secondary" type="button" data-close="1">Close</button>
      </div>

      <input type="hidden" id="modal_doctor_id" />
      <input type="hidden" id="modal_day" />
      <input type="hidden" id="modal_slot" />
      <input type="hidden" id="modal_course_id" />
    </div>
  </div>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/schedule_builder.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initScheduleBuilder?.();
  </script>
</body>
</html>
