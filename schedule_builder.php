<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="dashboard">
  <?php render_portal_navbar('schedule_builder.php'); ?>

  <div class="layout layout-single">
    <div class="schedule-builder-layout">
      <aside class="schedule-hours-panel">
        <div class="panel-header">
          <h3>Hours Report</h3>
          <p class="muted">Matches the Hours Report page (sorted by remaining hours).</p>
        </div>
        <div id="scheduleHoursStatus" class="status" role="status" aria-live="polite"></div>
        <div id="scheduleHoursList" class="hours-report-panel"></div>
      </aside>
      <main class="main">
      <header class="main-header">
        <div>
          <h2>Build Doctor Schedules</h2>
          <p class="muted">Select week and doctor, then click slots to assign courses</p>
        </div>
      </header>

      <!-- Main Controls - Single Row -->
      <section class="card" style="margin-bottom:20px;">
        <div style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
          
          <!-- Week Selection -->
          <div class="field" style="flex:1; min-width:220px;">
            <label class="field-label" for="weekSelect">Week</label>
            <select id="weekSelect" class="form-control">
              <option value="">Loading weeks…</option>
            </select>
          </div>

          <!-- Doctor Selection -->
          <div class="field" style="flex:1; min-width:220px;">
            <label class="field-label" for="doctorSelect">Doctor</label>
            <select id="doctorSelect" class="form-control">
              <option value="">Loading doctors…</option>
            </select>
          </div>

          <!-- Year Filter -->
          <div class="field" style="min-width:120px;">
            <label class="field-label" for="builderYearFilterMain">Year</label>
            <select id="builderYearFilterMain" class="form-control">
              <option value="">All</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
            </select>
          </div>

          <!-- Semester Filter -->
          <div class="field" style="min-width:120px;">
            <label class="field-label" for="builderSemesterFilterMain">Semester</label>
            <select id="builderSemesterFilterMain" class="form-control">
              <option value="">All</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
          </div>

          <!-- Refresh Button -->
          <button id="refreshSchedule" class="btn btn-secondary" type="button">Refresh</button>
        </div>
      </section>

      <!-- Secondary Controls - Collapsible -->
      <details class="card" style="margin-bottom:20px; overflow:hidden;">
        <summary style="cursor:pointer; padding:16px 20px; font-weight:600; user-select:none; display:flex; align-items:center; justify-content:space-between; background:var(--surface-2); border-bottom:1px solid var(--card-border); transition:all 0.2s ease;">
          <span style="display:flex; align-items:center; gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="transition:transform 0.2s ease;">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Advanced Options
          </span>
          <span class="muted" style="font-size:0.85rem;">Week management, exports, unavailability & day cancellation</span>
        </summary>
        
        <div style="padding:24px;">
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px;">
            
            <!-- Week Management -->
            <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                  <path d="M3 10h18M9 4v4M15 4v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <h3 style="font-size:0.95rem; font-weight:700; margin:0;">Week Management</h3>
              </div>
              
              <div style="display:flex; flex-direction:column; gap:12px;">
                <!-- Create New Week -->
                <div>
                  <div class="muted" style="font-size:0.8rem; margin-bottom:8px;">Create New Week</div>
                  <div style="display:flex; gap:8px;">
                    <input id="weekStartDate" class="form-control" type="date" style="flex:1; font-size:0.875rem;" placeholder="Start date" />
                    <select id="weekTypeSelect" class="form-control" style="width:110px; font-size:0.875rem;">
                      <option value="ACTIVE">Active</option>
                      <option value="PREP">Prep</option>
                      <option value="RAMADAN">Ramadan</option>
                    </select>
                    <button id="startWeekBtn" class="btn btn-primary btn-small" type="button">Create</button>
                  </div>
                </div>
                
                <div style="height:1px; background:var(--card-border);"></div>
                
                <!-- Modify Selected Week -->
                <div>
                  <div class="muted" style="font-size:0.8rem; margin-bottom:8px;">Change Week Type</div>
                  <div style="display:flex; gap:8px;">
                    <select id="weekTypeUpdate" class="form-control" style="flex:1; font-size:0.875rem;">
                      <option value="">Change type to...</option>
                      <option value="ACTIVE">Active</option>
                      <option value="PREP">Prep</option>
                      <option value="RAMADAN">Ramadan</option>
                    </select>
                    <button id="updateWeekTypeBtn" class="btn btn-secondary btn-small" type="button">Update</button>
                  </div>
                </div>
              </div>
              
              <div id="weekManagementStatus" class="status" role="status" aria-live="polite" style="margin-top:12px; font-size:0.85rem;"></div>
            </div>

            <!-- Export Options -->
            <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5-5m0 0l5 5m-5-5v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 style="font-size:0.95rem; font-weight:700; margin:0;">Export Schedules</h3>
              </div>
              
              <div style="display:flex; flex-direction:column; gap:8px;">
                <button id="exportDoctorXls" class="btn btn-secondary btn-small" type="button">
                  <span>Export Selected Doctor</span>
                </button>
                <button id="exportAllDoctorsXls" class="btn btn-secondary btn-small" type="button">
                  <span>Export All Doctors</span>
                </button>
                <button id="exportPrepWeeksXls" class="btn btn-secondary btn-small" type="button">
                  <span>Export Prep Weeks</span>
                </button>
                
                <div style="height:1px; background:var(--card-border); margin:8px 0;"></div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                  <button id="exportDoctorEmail" class="btn btn-secondary btn-small" type="button" title="Email schedule">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="display:inline-block; margin-right:4px;">
                      <path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/>
                    </svg>
                    Email
                  </button>
                  <a id="exportDoctorWhatsApp" class="btn btn-secondary btn-small" href="" target="_blank" rel="noopener" title="WhatsApp schedule" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="display:inline-block; margin-right:4px;">
                      <path fill="currentColor" d="M20.5 3.5A11 11 0 0 0 2.9 17.8L2 22l4.3-.9A11 11 0 0 0 20.5 3.5Zm-8.9 17a9 9 0 0 1-4.6-1.2l-.3-.2-2.6.6.6-2.5-.2-.3A9 9 0 1 1 11.6 20.5Zm5-6.4c-.3-.2-1.6-.8-1.9-.9s-.5-.1-.7.2-.8.9-1.1.4-.4.3-.2.1a7.4 7.4 0 0 1-2.2-1.4 8.2 8.2 0 0 1-1.5-1.9c-.2-.4 0-.6.2-.8l.4-.5c.1-.2.2-.4.3-.5.1-.2 0-.4 0-.6s-.7-1.7-1-2.3c-.3-.6-.6-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.1-1.2 2.8 1.2 3.3 1.4 3.5c.2.2 2.3 3.5 5.6 4.9.8.3 1.4.5 1.9.6.8.3 1.6.2 2.2.1.7-.1 2.1-.9 2.4-1.7.3-.8.3-1.5.2-1.7-.1-.2-.3-.3-.6-.5Z"/>
                    </svg>
                    WhatsApp
                  </a>
                </div>
              </div>
            </div>

            <!-- Unavailability -->
            <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                  <path d="M4.93 4.93l14.14 14.14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <h3 style="font-size:0.95rem; font-weight:700; margin:0;">Block Unavailable Times</h3>
              </div>
              
              <div style="display:flex; flex-direction:column; gap:10px;">
                <input id="unavailStart" class="form-control" type="datetime-local" placeholder="Start date & time" style="font-size:0.875rem;" />
                <input id="unavailEnd" class="form-control" type="datetime-local" placeholder="End date & time" style="font-size:0.875rem;" />
                <input id="unavailReason" class="form-control" type="text" placeholder="Reason (optional)" style="font-size:0.875rem;" />
                <button id="addUnavailBtn" class="btn btn-primary btn-small" type="button">Add Unavailability</button>
              </div>
              
              <div id="unavailStatus" class="status" role="status" aria-live="polite" style="margin-top:12px; font-size:0.85rem;"></div>
            </div>

            <!-- Cancel Day -->
            <div style="padding:20px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:12px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 style="font-size:0.95rem; font-weight:700; margin:0;">Cancel Entire Day</h3>
              </div>
              
              <div style="display:flex; flex-direction:column; gap:10px;">
                <select id="cancelDaySelect" class="form-control" style="font-size:0.875rem;">
                  <option value="Sun">Sunday</option>
                  <option value="Mon">Monday</option>
                  <option value="Tue">Tuesday</option>
                  <option value="Wed">Wednesday</option>
                  <option value="Thu">Thursday</option>
                </select>
                <input id="cancelReason" class="form-control" type="text" placeholder="Reason (optional)" style="font-size:0.875rem;" />
                <div style="display:flex; gap:8px;">
                  <button id="cancelDayBtn" class="btn btn-danger btn-small" type="button" style="flex:1;">Cancel Day</button>
                  <button id="uncancelDayBtn" class="btn btn-secondary btn-small" type="button" style="flex:1;">Undo</button>
                </div>
              </div>
              
              <div id="cancelStatus" class="status" role="status" aria-live="polite" style="margin-top:12px; font-size:0.85rem;"></div>
            </div>
          </div>

          <!-- Unavailability List -->
          <div id="unavailList" class="courses-list" style="margin-top:24px; padding-top:24px; border-top:1px solid var(--card-border);">
            <div class="muted" style="font-size:0.875rem;">No unavailability periods for this week.</div>
          </div>
        </div>
      </details>

      <style>
        details[open] summary svg {
          transform: rotate(180deg);
        }
        details summary:hover {
          background: var(--surface-1);
        }
      </style>

      <section class="panel">
        <div class="schedule-header">
          <div id="scheduleMetaHint" class="muted">Week starts Sunday • Each slot = 1 hour 30 minutes</div>
          <div class="page-actions">
            <div class="field" style="margin:0;"><label class="muted" style="font-size:0.85rem;" for="studentProgramSelect">Student Program</label><select id="studentProgramSelect" class="navlink"><option value="">Select program</option></select></div>
            <div class="field" style="margin:0;"><label class="muted" style="font-size:0.85rem;" for="studentYearSelect">Student Year</label><select id="studentYearSelect" class="navlink"><option value="">Year</option><option value="1">Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option></select></div>
            <div class="field" style="margin:0;"><label class="muted" style="font-size:0.85rem;" for="studentSemesterSelect">Student Sem</label><select id="studentSemesterSelect" class="navlink"><option value="">Sem</option><option value="1">Sem 1</option><option value="2">Sem 2</option></select></div>
            <button id="toggleStudentSchedule" class="btn btn-secondary btn-small" type="button">Show Student Schedule</button>
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
        </div>
      </div>
      </main>
    </div>
  </div>

  <!-- Simple modal for slot assignment -->
  <div id="studentScheduleMini" class="floating-schedule" aria-hidden="true">
    <div class="floating-schedule-header">
      <div>
        <div class="floating-schedule-title">Student Schedule</div>
        <div id="studentScheduleMeta" class="floating-schedule-subtitle">Select program/year/semester</div>
      </div>
      <div style="display:flex; gap:6px; align-items:center;">
        <button id="refreshStudentSchedule" class="btn btn-secondary btn-small" type="button">Refresh</button>
        <button id="closeStudentSchedule" class="btn btn-secondary btn-small" type="button">Close</button>
      </div>
    </div>
    <div id="studentScheduleStatus" class="status" role="status" aria-live="polite"></div>
    <div class="floating-schedule-body">
      <div class="schedule-wrap mini">
        <table class="schedule-grid mini" aria-label="Student schedule mini grid">
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
          <tbody id="studentScheduleBody"></tbody>
        </table>
      </div>
    </div>
  </div>

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
        <small class="hint">Enter the room or lab name/code (optional).</small>

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

        <hr class="section-divider" />

        <div class="field">
          <label for="modal_slot_cancel_reason">Slot cancellation reason (optional)</label>
          <input id="modal_slot_cancel_reason" type="text" placeholder="optional" />
          <small class="hint">Canceling a slot blocks scheduling in it (without canceling the whole day).</small>
        </div>

        <div id="modalConflict" class="status" role="status" aria-live="polite"></div>
        <div id="modalStatus" class="status" role="status" aria-live="polite"></div>
      </div>

      <div class="modal-actions">
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

  <script src="js/core.js?v=20260914e"></script>
  <script src="js/navbar.js?v=20260914e"></script>
  <script src="js/schedule_builder.js?v=20260919z"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initScheduleBuilder?.();
  </script>
</body>
</html>
