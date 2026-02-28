<?php

declare(strict_types=1);

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
  <title>Availability</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body class="availability-view">
  <?php render_portal_navbar('availability.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1 id="availabilityTitle">Availability</h1>
      <p class="subtitle" id="availabilitySubtitle">Mark available slots for each week.</p>
    </header>

    <section class="card">
      <div class="schedule-header">
        <div class="filter-bar">
          <div class="field">
            <label for="availabilityWeekSelect">Week</label>
            <select id="availabilityWeekSelect">
              <option value="">Loading…</option>
            </select>
          </div>

          <div class="field">
            <label for="availabilityDoctorSelect">Doctor</label>
            <select id="availabilityDoctorSelect">
              <option value="">Loading…</option>
            </select>
          </div>
        </div>

        <div class="page-actions">
          <div id="availabilityStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </div>

      <div class="schedule-wrap">
        <table class="schedule-grid" aria-label="Availability grid">
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
          <tbody id="availabilityScheduleBody"></tbody>
        </table>
      </div>

      <div class="legend">
        <span class="pill pill-r">A</span><span class="muted">Available</span>
        <span class="muted">Click a slot to toggle availability.</span>
      </div>
    </section>
  </main>

  <div id="availabilityDoctorsModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="availabilityDoctorsTitle">
    <div class="modal-backdrop" data-close="1"></div>
    <div class="modal-card" style="width:min(720px, 96vw);">
      <div class="modal-header">
        <h3 id="availabilityDoctorsTitle">Available Doctors</h3>
        <button class="btn btn-secondary btn-small" type="button" data-close="1">Close</button>
      </div>
      <div class="modal-body">
        <div id="availabilityDoctorsList" class="courses-list">
          <div class="muted">Loading…</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.AVAILABILITY_DOCTOR_ID = <?php echo json_encode($doctorId); ?>;
    window.AVAILABILITY_ROLE = <?php echo json_encode($role); ?>;
    window.AVAILABILITY_IS_ADMIN = <?php echo json_encode($role === 'admin' || $role === 'management'); ?>;
  </script>
  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script src="js/availability.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initAvailabilityView?.({
      doctorId: window.AVAILABILITY_DOCTOR_ID,
      role: window.AVAILABILITY_ROLE
    });
  </script>
</body>
</html>
