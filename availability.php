<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('availability.php');

$u = auth_current_user();
$role = (string)($u['role'] ?? '');

if ($role !== 'admin' && $role !== 'management') {
    auth_require_roles(['teacher']);
}

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($role === 'teacher') {
    $doctorId = (int)($u['doctor_id'] ?? 0);
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Availability Management</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/availability.css?v=20261213a" />
</head>
<body class="availability-view">
  <?php render_portal_navbar('availability.php'); ?>

  <main class="container container-top">
    <!-- Professional Glass Header -->
    <div class="lectures-header">
      <div class="lectures-header-content">
        <h1 class="lectures-title" id="availabilityTitle">Availability Management</h1>
        <p class="lectures-subtitle" id="availabilitySubtitle">Set your available teaching slots for the active week</p>
      </div>
    </div>

    <!-- Summary Stats -->
    <section class="card" style="margin-bottom:20px;">
      <div style="padding:14px 16px; background:var(--surface-2); border:1px solid var(--card-border); border-radius:8px;">
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Available Slots</div>
            <div style="font-size:1.5rem; font-weight:700;" id="statsAvailableSlots">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Not Set</div>
            <div style="font-size:1.5rem; font-weight:700;" id="statsUnavailableSlots">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Blocked Periods</div>
            <div style="font-size:1.5rem; font-weight:700;" id="statsBlockedSlots">-</div>
          </div>
          <div>
            <div class="muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Availability</div>
            <div style="font-size:1.5rem; font-weight:700; color:var(--accent);" id="statsAvailabilityPercent">-</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Availability Card -->
    <section class="card">
      <!-- Enhanced Filter Bar -->
      <div class="availability-controls">
        <div class="availability-filters">
          <div class="field">
            <label for="availabilityWeekSelect" class="field-label">Week</label>
            <select id="availabilityWeekSelect" class="form-control">
              <option value="">Loading weeks…</option>
            </select>
          </div>

          <?php if ($role === 'admin' || $role === 'management'): ?>
          <div class="field">
            <label for="availabilityDoctorSelect" class="field-label">Doctor</label>
            <select id="availabilityDoctorSelect" class="form-control">
              <option value="">All Doctors</option>
            </select>
          </div>
          <?php else: ?>
          <div class="field">
            <label class="field-label">Doctor</label>
            <input type="text" id="availabilityDoctorName" class="form-control" readonly />
          </div>
          <?php endif; ?>
        </div>

        <div class="availability-actions">
          <button id="copyAvailabilityBtn" class="btn btn-secondary" type="button" title="Copy from previous week">
            <span class="btn-label">Copy from Previous</span>
          </button>
          <button id="clearAvailabilityBtn" class="btn btn-danger" type="button" title="Clear all availability for this week">
            <span class="btn-label">Clear Week</span>
          </button>
          <?php if ($role === 'admin' || $role === 'management'): ?>
          <button id="addUnavailabilityBtn" class="btn btn-secondary" type="button" title="Add unavailability period">
            <span class="btn-label">Add Unavailability</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Status Bar -->
      <div class="availability-status-bar">
        <div id="availabilityStatus" class="status" role="status" aria-live="polite"></div>
        <div id="availabilityStats" class="availability-stats"></div>
      </div>

      <!-- Schedule Grid -->
      <div class="schedule-wrap">
        <table class="schedule-grid availability-grid" aria-label="Availability grid">
          <thead>
            <tr>
              <th class="corner">Time Slot</th>
              <th>Sunday</th>
              <th>Monday</th>
              <th>Tuesday</th>
              <th>Wednesday</th>
              <th>Thursday</th>
            </tr>
          </thead>
          <tbody id="availabilityScheduleBody">
            <tr><td colspan="6" class="loading-cell">Loading availability...</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Legend -->
      <div class="availability-legend">
        <div class="legend-item">
          <span class="legend-swatch available"></span>
          <span class="legend-label">Available</span>
        </div>
        <div class="legend-item">
          <span class="legend-swatch unavailable"></span>
          <span class="legend-label">Not Set</span>
        </div>
        <div class="legend-item">
          <span class="legend-swatch blocked"></span>
          <span class="legend-label">Blocked Period</span>
        </div>
        <div class="legend-hint">
          Click any slot to toggle availability
        </div>
      </div>
    </section>

    <!-- Unavailability List Section (Admin/Management Only) -->
    <?php if ($role === 'admin' || $role === 'management'): ?>
    <section class="card" id="unavailabilitySection" style="display: none;">
      <h2 style="font-size:1.25rem; font-weight:700; margin:0 0 20px 0; padding-bottom:12px; border-bottom:1px solid var(--card-border);">Unavailability Periods</h2>

      <div id="unavailabilityList" class="unavailability-list">
        <div class="muted">Select a doctor and week to view unavailability periods.</div>
      </div>
    </section>
    <?php endif; ?>
  </main>

  <!-- Doctor List Modal (Admin View) -->
  <div id="availabilityDoctorsModal" class="modal-overlay" role="dialog" aria-labelledby="availabilityDoctorsTitle" aria-hidden="true">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="availabilityDoctorsTitle" class="modal-title">Available Doctors</h3>
        <button class="btn btn-secondary" type="button" data-close="modal">Close</button>
      </div>
      <div class="modal-body">
        <div id="availabilityDoctorsList" class="doctor-list">
          <div class="muted">Loading...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Unavailability Modal (Admin/Management) -->
  <?php if ($role === 'admin' || $role === 'management'): ?>
  <div id="unavailabilityModal" class="modal-overlay" role="dialog" aria-labelledby="unavailabilityModalTitle" aria-hidden="true">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="unavailabilityModalTitle" class="modal-title">Add Unavailability Period</h3>
        <button class="btn btn-secondary" type="button" data-close="modal">Close</button>
      </div>
      <div class="modal-body">
        <form id="unavailabilityForm" class="form">
          <div class="field">
            <label for="unavailStartDatetime" class="form-label">Start Date & Time</label>
            <input type="datetime-local" id="unavailStartDatetime" class="form-control" required />
          </div>

          <div class="field">
            <label for="unavailEndDatetime" class="form-label">End Date & Time</label>
            <input type="datetime-local" id="unavailEndDatetime" class="form-control" required />
          </div>

          <div class="field">
            <label for="unavailReason" class="form-label">Reason (Optional)</label>
            <textarea id="unavailReason" class="form-control" rows="3" placeholder="e.g., Conference, Medical leave, Personal"></textarea>
          </div>

          <div id="unavailStatus" class="status" role="status" aria-live="polite"></div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-close="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Period</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    window.AVAILABILITY_DOCTOR_ID = <?php echo json_encode($doctorId); ?>;
    window.AVAILABILITY_ROLE = <?php echo json_encode($role); ?>;
    window.AVAILABILITY_IS_ADMIN = <?php echo json_encode($role === 'admin' || $role === 'management'); ?>;
  </script>
  <script src="js/core.js?v=20260425a"></script>
  <script src="js/navbar.js?v=20260425a"></script>
  <script src="js/availability.js?v=20261213a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAvailabilityView?.({
      doctorId: window.AVAILABILITY_DOCTOR_ID,
      role: window.AVAILABILITY_ROLE
    });
  </script>
</body>
</html>
