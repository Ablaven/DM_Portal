(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml, makeCourseLabel, parseDoctorIdsCsv, applyPageFiltersToCourses, doesItemMatchGlobalFilters, getGlobalFilters, setGlobalFilters, initPageFiltersUI, buildMailtoHref, buildDoctorScheduleGreetingText, buildDoctorScheduleExportUrl, triggerBackgroundDownload, normalizePhoneForWhatsApp, buildWhatsAppSendUrl } = window.dmportal || {};

  // Dashboard scheduling UI
  // -----------------------------
  // Week starts Sunday. Weekend Fri/Sat are not scheduled.
  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_HOURS = 1.5;

  // Slot timing (updated):
  // 1) 8:30–10:00
  // 2) 10:10–11:30
  // 3) 11:40–1:00
  // 4) 1:10–2:40
  // 5) 2:50–4:20
  const SLOT_TIMES = {
  1: "8:30 AM–10:00 AM",
  2: "10:10 AM–11:30 AM",
  3: "11:40 AM–1:00 PM",
  4: "1:10 PM–2:40 PM",
  5: "2:50 PM–4:20 PM",
  };

  const STUDENT_SCHEDULE_DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];

  function slotLabel(slot) {
  const t = SLOT_TIMES[slot] || "";
  // Cleaner label than "#1 (...)" (also looks better in exports/screenshots).
  return t ? `Slot ${slot} • ${t}` : `Slot ${slot}`;
  }


  let state = {
  doctors: [],
  courses: [],
  weeks: [],
  activeWeekId: null,
  activeDoctorId: null,
  adminDoctorsWeekId: null,
  scheduleGrid: {}, // grid[day][slot] = course
  cancellations: {}, // cancellations[day] = reason
  slotCancellations: {}, // slotCancellations[day][slot] = reason
  unavailability: [], // list of ranges
  availabilityMap: {},
  };

  // Auto-filter note: tracks the last time filters were auto-switched for a doctor.
  // Set this when auto-switching Year/Sem filters based on a doctor's assigned courses.
  let lastBuilderAutoFilterNote = null;

  // Auto-set builder filters based on the selected doctor's courses.
  // Currently a no-op placeholder — can be implemented to auto-switch Year/Sem.
  function maybeAutoSetBuilderFiltersForDoctor(doctorId) {
    // Find the doctor's most common Year/Sem from their assigned courses and auto-switch filters.
    const did = Number(doctorId || 0);
    if (!did || !state.courses?.length) return;
    const doctorCourses = state.courses.filter((c) => {
      const ids = parseDoctorIdsCsv(c?.doctor_ids);
      if (ids.length) return ids.includes(did);
      return Number(c?.doctor_id || 0) === did;
    });
    if (!doctorCourses.length) return;
    // Tally year/sem combinations
    const tally = {};
    for (const c of doctorCourses) {
      const key = `${c.year_level}:${c.semester}`;
      tally[key] = (tally[key] || 0) + 1;
    }
    const best = Object.entries(tally).sort((a, b) => b[1] - a[1])[0];
    if (!best) return;
    const [year_level, semester] = best[0].split(':').map(Number);
    const current = getGlobalFilters ? getGlobalFilters() : {};
    if (current.year_level === year_level && current.semester === semester) return;
    if (setGlobalFilters) setGlobalFilters({ year_level, semester });
    lastBuilderAutoFilterNote = { doctor_id: did, year_level, semester, at: Date.now() };
  }

  function formatHours(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return "0.00";
  return num.toFixed(2);
  }

  function getWeekLabel(weekId) {
  const w = (state.weeks || []).find((x) => String(x.week_id) === String(weekId));
  return String(w?.label || "").trim();
  }

  function renderDoctorsSelect() {
  const select = document.getElementById("doctorSelect");
  if (!select) return;

  select.innerHTML = "";

  if (state.doctors.length === 0) {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "No doctors found";
    select.appendChild(opt);
    select.disabled = true;
    return;
  }

  select.disabled = false;
  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.textContent = "Select a doctor";
  select.appendChild(placeholder);

  for (const d of state.doctors) {
    const opt = document.createElement("option");
    opt.value = String(d.doctor_id);
    opt.textContent = d.full_name;
    select.appendChild(opt);
  }

  if (state.activeDoctorId) {
    select.value = String(state.activeDoctorId);
  }
  }

  function renderDoctorHoursCard(doctor) {
    const totals = doctor.totals || {};
    const allocT = Number(totals.allocated_hours || 0);
    const doneT = Number(totals.done_hours || 0);
    const remT = Number(totals.remaining_hours || 0);
    const pct = allocT > 0 ? Math.max(0, Math.min(100, (doneT / allocT) * 100)) : 0;

    const courseRows = (doctor.courses || [])
      .filter((course) => Number(course?.remaining_hours || 0) > 0)
      .map((course) => {
        const alloc = Number(course.allocated_hours || 0);
        const done = Number(course.done_hours || 0);
        const rem = Number(course.remaining_hours || 0);
        const title = String(course.course_name || "(Unnamed course)");
        const metaParts = [];
        if (course.program) metaParts.push(course.program);
        if (course.subject_code) metaParts.push(course.subject_code);
        if (course.year_level) metaParts.push(`Year ${course.year_level}`);
        if (course.semester) metaParts.push(`Sem ${course.semester}`);
        const meta = metaParts.length ? metaParts.join(" • ") : "";

        return `
          <div class="course-progress-item subject-progress-item">
            <div class="course-progress-top">
              <div>
                <div class="course-progress-title">${escapeHtml(title)}</div>
                <div class="course-progress-meta">${escapeHtml(meta)}</div>
              </div>
              <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
                <span class="badge badge-success">Done ${formatHours(done)}h</span>
                <span class="badge badge-danger">Remaining ${formatHours(rem)}h</span>
                <span class="muted">${formatHours(done)}h / ${formatHours(alloc)}h</span>
              </div>
            </div>
            <div class="course-progress-bar" aria-label="Course progress">
              <div class="course-progress-fill" style="width:${alloc > 0 ? ((done / alloc) * 100).toFixed(2) : 0}%"></div>
            </div>
            <div class="course-progress-legend">
              <span class="badge badge-success">Done: ${formatHours(done)}h</span>
              <span class="badge badge-danger">Remaining: ${formatHours(rem)}h</span>
              <span class="muted">${alloc > 0 ? Math.round((done / alloc) * 100) : 0}%</span>
            </div>
          </div>
        `;
      })
      .join("");

    return `
      <div class="course-progress-item doctor-progress-item" style="margin-bottom:18px;">
        <div class="course-progress-top">
          <div>
            <div class="course-progress-title">${escapeHtml(doctor.full_name || "")}</div>
            <div class="course-progress-meta">Doctor ID: ${escapeHtml(doctor.doctor_id)} • ${doctor.courses?.length || 0} courses</div>
          </div>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
            <span class="badge">Allocated ${formatHours(allocT)}h</span>
            <span class="badge badge-success">Done ${formatHours(doneT)}h</span>
            <span class="badge badge-danger">Remaining ${formatHours(remT)}h</span>
            <span class="muted">${formatHours(doneT)}h / ${formatHours(allocT)}h</span>
          </div>
        </div>
        <div class="course-progress-bar" aria-label="Doctor progress">
          <div class="course-progress-fill" style="width:${pct.toFixed(2)}%"></div>
        </div>
        <div class="course-progress-legend">
          <span class="badge badge-success">Done: ${formatHours(doneT)}h</span>
          <span class="badge badge-danger">Remaining: ${formatHours(remT)}h</span>
          <span class="muted">${pct.toFixed(0)}%</span>
        </div>
        <div class="hours-report-courses">${courseRows}</div>
      </div>
    `;
  }

  async function loadHoursRemainingPanel() {
    const status = document.getElementById("scheduleHoursStatus");
    const list = document.getElementById("scheduleHoursList");
    if (!status || !list) return;

    function setStatus(msg, type) {
      status.textContent = msg || "";
      status.classList.remove("success", "error");
      if (type) status.classList.add(type);
    }

    setStatus("Loading...");
    try {
      const filters = getGlobalFilters ? getGlobalFilters() : {};
      const qs = new URLSearchParams();
      if (filters?.year_level) qs.set("year_level", String(filters.year_level));
      if (filters?.semester) qs.set("semester", String(filters.semester));
      const url = "php/get_hours_report.php" + (qs.toString() ? `?${qs.toString()}` : "");
      const payload = await fetchJson(url);
      if (!payload?.success) throw new Error(payload?.error || "Failed to load hours report.");
      const doctors = payload?.data?.doctors || [];
      const ordered = doctors
        .map((doc) => ({
          ...doc,
          remaining: Number(doc?.totals?.remaining_hours || 0),
        }))
        .filter((doc) => doc.remaining > 0)
        .sort((a, b) => b.remaining - a.remaining);

      if (!ordered.length) {
        list.innerHTML = '<div class="muted">No doctors with remaining hours found.</div>';
        setStatus("");
        return;
      }

      list.innerHTML = ordered.map(renderDoctorHoursCard).join("");
      setStatus("");
    } catch (err) {
      list.innerHTML = '<div class="muted">Unable to load hours report.</div>';
      setStatus(err.message || "Failed to load hours report.", "error");
    }
  }

  function renderCoursesSidebar() {
  const list = document.getElementById("coursesList");
  if (!list) return;

  const filtered = getFilteredCoursesForUI().filter((course) => Number(course?.remaining_hours || 0) > 0);
  if (!filtered.length) {
    list.innerHTML = `<div class="muted">No courses found for the selected filters.</div>`;
    return;
  }

  list.innerHTML = "";
  for (const c of filtered) {
    const item = document.createElement("div");
    item.className = "course-item";

    const top = document.createElement("div");
    top.className = "course-top";

    const left = document.createElement("div");
    left.innerHTML = `
      <div>
        <div class="muted" style="font-size:0.85rem; margin-top:2px;">${escapeHtml(c.program)}</div>
        <div class="muted" style="font-size:0.85rem; margin-top:2px;">Year ${escapeHtml(c.year_level)} • Sem ${escapeHtml(c.semester)}</div>
        <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap; margin-top:4px;">
          <span class="pill">${escapeHtml(makeCourseLabel(c.course_type, c.subject_code))}</span>
          <div><strong>${escapeHtml(c.course_name)}</strong></div>
        </div>
      </div>
    `;

    const badge = document.createElement("span");
    badge.className = "badge badge-hours";
    badge.textContent = `${formatHours(c.remaining_hours)}h`;

    top.appendChild(left);
    top.appendChild(badge);
    item.appendChild(top);
    list.appendChild(item);
  }
  }

  function setHiddenCourseId(v) {
  const hid = document.getElementById("modal_course_id");
  if (hid) hid.value = v ? String(v) : "";
  }

  function getHiddenCourseId() {
  return document.getElementById("modal_course_id")?.value || "";
  }

  function courseDisplayLine(c) {
  // Requested layout:
  // 1) Program
  // 2) Year + Semester
  // 3) Code + Name
  return `${c.program} | Year ${c.year_level} Sem ${c.semester} | ${makeCourseLabel(c.course_type, c.subject_code)} ${c.course_name}`;
  }

  function getCoursesForActiveDoctorModal() {
  // For Schedule Builder slot modal:
  // show ONLY courses assigned to the currently selected doctor.
  // Supports multi-doctor assignment via get_courses.php -> doctor_ids CSV.
  const all = getFilteredCoursesForUI();
  const did = Number(state.activeDoctorId || 0);
  if (!did) return all;

  return all.filter((c) => {
    // Prefer multi-doctor mapping if available
    const ids = parseDoctorIdsCsv(c?.doctor_ids);
    if (ids.length) return ids.includes(did);
    // Fallback to legacy single doctor_id
    return Number(c?.doctor_id || 0) === did;
  });
  }

  function populateModalCourses() {
  const codeSel = document.getElementById("modal_course_code");
  const nameSel = document.getElementById("modal_course_name");
  if (!codeSel || !nameSel) return;

  const courses = getCoursesForActiveDoctorModal();
  const currentId = getHiddenCourseId();

  codeSel.innerHTML = `<option value="">Select a code</option>`;
  nameSel.innerHTML = `<option value="">Select a course</option>`;

  for (const c of courses) {
    // Course Code dropdown
    const optCode = document.createElement("option");
    optCode.value = String(c.course_id);
    // NOTE: course codes may be duplicated, so include type + year/sem + name for clarity.
    const codeLabel = String(c.subject_code || "").trim();
    optCode.textContent = codeLabel
      ? `${makeCourseLabel(c.course_type, c.subject_code)} • Year ${c.year_level} Sem ${c.semester} • ${c.course_name}`
      : `(ID ${c.course_id})`;
    codeSel.appendChild(optCode);

    // Course Name dropdown
    const optName = document.createElement("option");
    optName.value = String(c.course_id);
    optName.textContent = `${courseDisplayLine(c)} (${formatHours(c.remaining_hours)}h left)`;
    nameSel.appendChild(optName);
  }

  // Keep selection in sync after repopulating
  if (currentId) {
    codeSel.value = String(currentId);
    nameSel.value = String(currentId);
  }
  }

  function parseLocalDateTime(s) {
  // Accepts "YYYY-MM-DD HH:mm:ss" or ISO; returns Date or null
  if (!s) return null;
  const iso = String(s).includes("T") ? String(s) : String(s).replace(" ", "T");
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? null : d;
  }

  function slotDateRange(day, slot) {
  // Uses week start date from state.weeks
  const week = (state.weeks || []).find((w) => String(w.week_id) === String(state.activeWeekId));
  if (!week?.start_date) return null;

  const base = new Date(String(week.start_date) + "T00:00:00");
  const offsets = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4 };
  const dayOffset = offsets[day];
  if (dayOffset === undefined) return null;

  const startTimes = { 1: "08:30", 2: "10:10", 3: "11:40", 4: "13:10", 5: "14:50" };
  const endTimes = { 1: "10:00", 2: "11:30", 3: "13:00", 4: "14:40", 5: "16:20" };

  const start = new Date(base);
  start.setDate(start.getDate() + dayOffset);
  const [sh, sm] = startTimes[slot].split(":").map(Number);
  start.setHours(sh, sm, 0, 0);

  const end = new Date(base);
  end.setDate(end.getDate() + dayOffset);
  const [eh, em] = endTimes[slot].split(":").map(Number);
  end.setHours(eh, em, 0, 0);

  return { start, end };
  }

  function isSlotUnavailable(day, slot) {
  const r = slotDateRange(day, slot);
  if (!r) return false;
  for (const u of state.unavailability || []) {
    const us = parseLocalDateTime(u.start_datetime);
    const ue = parseLocalDateTime(u.end_datetime);
    if (!us || !ue) continue;
    // overlap
    if (us < r.end && ue > r.start) return true;
  }
  return false;
  }

  function hasAvailability(day, slot) {
  return Boolean(state.availabilityMap?.[day]?.[String(slot)]?.length);
  }

  function availabilityListForSlot(day, slot) {
  return state.availabilityMap?.[day]?.[String(slot)] || [];
  }

  function normalizeAvailabilityDay(day) {
  const d = String(day || "").trim().toUpperCase();
  const map = { SUN: "Sun", MON: "Mon", TUE: "Tue", WED: "Wed", THU: "Thu" };
  return map[d] || "";
  }

  async function refreshAvailability() {
  if (!state.activeDoctorId || !state.activeWeekId) return;
  try {
    const qs = new URLSearchParams({
      doctor_id: String(state.activeDoctorId),
      week_id: String(state.activeWeekId),
    });
    const payload = await fetchJson(`php/get_doctor_availability.php?${qs.toString()}`);
    if (!payload.success) throw new Error(payload.error || "Failed to load availability");

    const items = payload.data?.items || [];
    const map = {};
    for (const item of items) {
      const dayVal = normalizeAvailabilityDay(item.day_of_week);
      const slotVal = String(item.slot_number || "").trim();
      if (!dayVal || !slotVal) continue;
      if (!map[dayVal]) map[dayVal] = {};
      if (!map[dayVal][slotVal]) map[dayVal][slotVal] = [];
      map[dayVal][slotVal].push(item);
    }

    state.availabilityMap = map;
  } catch {
    state.availabilityMap = {};
  }
  }

  function removeBuilderPopup() {
  const existing = document.querySelector(".availability-popup.builder-popup");
  if (existing) existing.remove();
  }

  function renderAvailabilityPopup(anchor, day, slot) {
  const items = availabilityListForSlot(day, slot);
  if (!items.length || !anchor) return;

  removeBuilderPopup();

  const popup = document.createElement("div");
  popup.className = "availability-popup builder-popup";
  const names = items.map((i) => i.full_name).filter(Boolean);
  const list = names.length ? names.join(" • ") : "Doctor available";
  popup.innerHTML = `<div class="availability-popup-title">Doctor Available</div><div class="availability-popup-body">${escapeHtml(list)}</div>`;

  document.body.appendChild(popup);

  const rect = anchor.getBoundingClientRect();
  popup.style.left = `${rect.left + window.scrollX}px`;
  popup.style.top = `${rect.bottom + window.scrollY + 8}px`;

  window.setTimeout(() => popup.classList.add("open"), 10);
  }

  function renderUnavailabilityList() {
  const wrap = document.getElementById("unavailList");
  if (!wrap) return;

  const items = state.unavailability || [];
  if (!items.length) {
    wrap.innerHTML = `<div class="muted">No unavailability added for this week.</div>`;
    return;
  }

  wrap.innerHTML = "";
  for (const u of items) {
    const card = document.createElement("div");
    card.className = "course-item";
    const start = escapeHtml(u.start_datetime);
    const end = escapeHtml(u.end_datetime);
    const reason = escapeHtml(u.reason || "");
    card.innerHTML = `
      <div class="course-top">
        <div><strong>Unavailable</strong></div>
        <button class="btn btn-secondary btn-small" type="button" data-unavail-del="1" data-id="${escapeHtml(u.unavailability_id)}">Remove</button>
      </div>
      <div class="muted" style="font-size:0.9rem;">${start} → ${end}${reason ? " • " + reason : ""}</div>
    `;
    wrap.appendChild(card);
  }
  }

  async function refreshUnavailability() {
  if (!state.activeDoctorId || !state.activeWeekId) return;
  try {
    const qs = new URLSearchParams({ doctor_id: String(state.activeDoctorId), week_id: String(state.activeWeekId) });
    const payload = await fetchJson(`php/get_unavailability.php?${qs.toString()}`);
    if (!payload.success) throw new Error(payload.error || "Failed");
    state.unavailability = payload.data?.items || [];
    renderUnavailabilityList();
  } catch (err) {
    setStatusById("unavailStatus", err.message, "error");
  }
  }

  async function setActiveDoctor(doctorId) {
  state.activeDoctorId = doctorId;
  renderDoctorsSelect();

  try {
  maybeAutoSetBuilderFiltersForDoctor(doctorId);
  } catch {
  // ignore
  }

  setStatusById("scheduleStatus", "Loading…");
  await loadSchedule(doctorId);
  await refreshUnavailability();
  await refreshAvailability();
  renderScheduleMetaHint();
  renderScheduleGrid();
  updateDoctorExportShareLinks();
  setStatusById("scheduleStatus", "");
  }

  function renderScheduleMetaHint() {
  const el = document.getElementById("scheduleMetaHint");
  if (!el) return;

  const wkLabel = getWeekLabel(state.activeWeekId) || (state.activeWeekId ? `Week ${state.activeWeekId}` : "");
  const f = getGlobalFilters();
  const y = f?.year_level ? `Year ${f.year_level}` : "All Years";
  const s = f?.semester ? `Sem ${f.semester}` : "All Sem";

  const parts = [];
  if (wkLabel) parts.push(wkLabel);
  parts.push("Week starts Sunday");
  parts.push("Each slot = 1 hour 30 minutes");
  parts.push(`Scope: ${y} • ${s}`);

  el.textContent = parts.join(" • ");
  }

  function renderScheduleGrid() {
  const body = document.getElementById("scheduleBody");
  if (!body) return;

  body.innerHTML = "";

  for (const slot of SLOTS) {
    const tr = document.createElement("tr");

    const th = document.createElement("th");
    th.innerHTML = `<div class="slot-hdr"><div class="slot-hdr-num">Slot ${slot}</div><div class="slot-hdr-time">${escapeHtml(SLOT_TIMES[slot] || "")}</div></div>`;
    tr.appendChild(th);

    for (const day of DAYS) {
      const td = document.createElement("td");
      const cell = document.createElement("div");
      cell.className = "slot";

      // Cancelled day: block all slots
      if (state.cancellations?.[day] !== undefined) {
        const reason = state.cancellations[day];
        cell.classList.add("filled");
        cell.style.background = "#99999922";
        cell.style.borderColor = "#99999988";
        cell.innerHTML = `
          <div class="slot-title">Canceled</div>
          <div class="slot-sub">${reason ? reason : "Doctor not coming"}</div>
        `;
        cell.style.cursor = "not-allowed";
        td.appendChild(cell);
        tr.appendChild(td);
        continue;
      }

      // Cancelled slot: show as cancelled BUT allow opening the modal to UN-cancel.
      if (state.slotCancellations?.[day]?.[String(slot)] !== undefined) {
        const reason = state.slotCancellations[day][String(slot)];
        cell.classList.add("filled");
        cell.style.background = "#99999922";
        cell.style.borderColor = "#99999988";
        cell.innerHTML = `
          <div class="slot-title">Canceled</div>
          <div class="slot-sub">${reason ? reason : "Slot canceled"}</div>
          <div class="slot-sub slot-sub-muted">Click to undo</div>
        `;
        cell.style.cursor = "pointer";
        cell.addEventListener("click", () => openSlotModal(day, slot, null));
        td.appendChild(cell);
        tr.appendChild(td);
        continue;
      }

      // Unavailable slot: block and render
      if (isSlotUnavailable(day, slot)) {
        cell.classList.add("filled");
        cell.classList.add("type-unavail");
        cell.innerHTML = `
          <div class="slot-title">Unavailable</div>
          <div class="slot-sub">Blocked</div>
        `;
        cell.style.cursor = "not-allowed";
        td.appendChild(cell);
        tr.appendChild(td);
        continue;
      }

      const hasAvail = hasAvailability(day, slot);
      if (hasAvail) {
        cell.classList.add("available-slot");
        cell.addEventListener("mouseenter", () => renderAvailabilityPopup(cell, day, slot));
        cell.addEventListener("mouseleave", () => removeBuilderPopup());
      }

      const assigned = state.scheduleGrid?.[day]?.[String(slot)];
      const assignedMatchesFilters = assigned ? doesItemMatchGlobalFilters(assigned) : true;

      if (assigned) {
        cell.classList.add("filled");

        // If the assigned course is for a different Year/Sem, show it as occupied/locked.
        if (!assignedMatchesFilters) {
          cell.style.background = "#99999922";
          cell.style.borderColor = "#99999988";
          cell.innerHTML = `
            <div class="slot-title">Occupied</div>
            <div class="slot-sub">Other Year/Sem course</div>
          `;
          cell.style.cursor = "not-allowed";
          td.appendChild(cell);
          tr.appendChild(td);
          continue;
        }

        // Color is per doctor (background/border set below); do not style by course type.
        if (assigned.doctor_color) {
          cell.style.background = assigned.doctor_color + "22";
          cell.style.borderColor = assigned.doctor_color + "88";
        }
        const room = assigned.room_code ? `Room ${escapeHtml(assigned.room_code)}` : "";
        cell.innerHTML = `
          <div class="slot-title">${escapeHtml(assigned.course_name)}</div>
          <div class="slot-sub">${escapeHtml(makeCourseLabel(assigned.course_type, assigned.subject_code))}${room ? " • " + room : ""}</div>
        `;
      } else {
        cell.innerHTML = `
          <div class="slot-title">Empty</div>
          <div class="slot-sub">Click to assign</div>
        `;
      }

      cell.addEventListener("click", () => openSlotModal(day, slot, assigned));
      td.appendChild(cell);
      tr.appendChild(td);

      // reset styles when reused
      if (!assigned) {
        cell.style.background = "";
        cell.style.borderColor = "";
      }
    }

    body.appendChild(tr);
  }
  }

  function renderStudentMiniGrid(grid = {}) {
    const body = document.getElementById("studentScheduleBody");
    if (!body) return;

    body.innerHTML = "";
    for (const slot of SLOTS) {
      const tr = document.createElement("tr");
      const th = document.createElement("th");
      th.innerHTML = `<div class="slot-hdr"><div class="slot-hdr-num">Slot ${slot}</div><div class="slot-hdr-time">${escapeHtml(SLOT_TIMES[slot] || "")}</div></div>`;
      tr.appendChild(th);

      for (const day of STUDENT_SCHEDULE_DAYS) {
        const td = document.createElement("td");
        const cell = document.createElement("div");
        cell.className = "slot";

        const assigned = grid?.[day]?.[String(slot)];
        if (assigned) {
          cell.classList.add("filled");
          if (assigned.doctor_color) {
            cell.style.background = assigned.doctor_color + "22";
            cell.style.borderColor = assigned.doctor_color + "88";
          }
          const label = makeCourseLabel(assigned.course_type, assigned.subject_code);
          cell.innerHTML = `
            <div class="slot-title">${escapeHtml(assigned.course_name || "")}</div>
            <div class="slot-sub">${escapeHtml(label)} • ${escapeHtml(assigned.doctor_name || "")}</div>
          `;
        } else {
          cell.innerHTML = `
            <div class="slot-title">Empty</div>
          `;
        }

        td.appendChild(cell);
        tr.appendChild(td);
      }

      body.appendChild(tr);
    }
  }

  function updateStudentScheduleMeta(program, year, semester) {
    const meta = document.getElementById("studentScheduleMeta");
    if (!meta) return;
    if (!program || !year || !semester) {
      meta.textContent = "Select program/year/semester";
      return;
    }
    meta.textContent = `${program} • Year ${year} • Sem ${semester}`;
  }

  function renderStudentProgramOptions(coursesOverride = null) {
    const select = document.getElementById("studentProgramSelect");
    if (!select) return;
    const source = coursesOverride || state.courses || [];
    const programs = Array.from(
      new Set(source.map((c) => String(c.program || "").trim()).filter(Boolean))
    ).sort();

    const current = select.value;
    select.innerHTML = '<option value="">Select program</option>';
    if (!programs.length) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "No programs found";
      opt.disabled = true;
      select.appendChild(opt);
      return;
    }
    programs.forEach((p) => {
      const opt = document.createElement("option");
      opt.value = p;
      opt.textContent = p;
      select.appendChild(opt);
    });
    if (current) select.value = current;
  }

  async function refreshStudentProgramOptions() {
    try {
      if (!state.courses?.length) {
        await loadCourses();
      }
      renderStudentProgramOptions();
    } catch {
      renderStudentProgramOptions([]);
    }
  }

  function getStudentScheduleFilters() {
    const program = document.getElementById("studentProgramSelect")?.value || "";
    const year = Number(document.getElementById("studentYearSelect")?.value || 0);
    const semester = Number(document.getElementById("studentSemesterSelect")?.value || 0);
    return { program, year, semester };
  }

  async function refreshStudentScheduleMini() {
    const { program, year, semester } = getStudentScheduleFilters();
    const status = document.getElementById("studentScheduleStatus");

    updateStudentScheduleMeta(program, year, semester);

    if (!program || !year || !semester) {
      renderStudentMiniGrid({});
      if (status) status.textContent = "Select program/year/semester to load.";
      return;
    }

    if (status) {
      status.textContent = "Loading…";
      status.className = "status";
    }

    try {
      const qs = new URLSearchParams({
        program,
        year_level: String(year),
        semester: String(semester),
      });
      if (state.activeWeekId) qs.set("week_id", String(state.activeWeekId));

      const payload = await fetchJson(`php/get_student_schedule.php?${qs.toString()}`);
      if (!payload?.success) throw new Error(payload?.error || "Failed to load student schedule.");
      renderStudentMiniGrid(payload?.data?.grid || {});
      if (status) status.textContent = "";
    } catch (err) {
      renderStudentMiniGrid({});
      if (status) {
        status.textContent = err.message || "Failed to load student schedule.";
        status.className = "status error";
      }
    }
  }

  function setStudentMiniOpen(isOpen) {
    const panel = document.getElementById("studentScheduleMini");
    if (!panel) return;
    panel.classList.toggle("open", isOpen);
    panel.setAttribute("aria-hidden", isOpen ? "false" : "true");
  }

  function initStudentScheduleMini() {
    const panel = document.getElementById("studentScheduleMini");
    if (!panel) return;

    renderStudentProgramOptions();
    renderStudentMiniGrid({});

    const toggleBtn = document.getElementById("toggleStudentSchedule");
    toggleBtn?.addEventListener("click", async () => {
      const isOpen = panel.classList.contains("open");
      setStudentMiniOpen(!isOpen);
      if (!isOpen) {
        await refreshStudentProgramOptions();
        refreshStudentScheduleMini();
      }
    });

    document.getElementById("closeStudentSchedule")?.addEventListener("click", () => {
      setStudentMiniOpen(false);
    });

    document.getElementById("refreshStudentSchedule")?.addEventListener("click", () => {
      refreshStudentScheduleMini();
    });

    ["studentProgramSelect", "studentYearSelect", "studentSemesterSelect"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", () => {
        refreshStudentScheduleMini();
      });
    });

    const header = panel.querySelector(".floating-schedule-header");
    let dragState = null;

    const stored = localStorage.getItem("dmportal_student_schedule_pos");
    if (stored) {
      try {
        const pos = JSON.parse(stored);
        if (typeof pos?.left === "number" && typeof pos?.top === "number") {
          panel.style.left = `${pos.left}px`;
          panel.style.top = `${pos.top}px`;
          panel.style.right = "auto";
          panel.style.bottom = "auto";
        }
      } catch {
        // ignore
      }
    }

    header?.addEventListener("mousedown", (e) => {
      if (e.button !== 0) return;
      const rect = panel.getBoundingClientRect();
      dragState = {
        offsetX: e.clientX - rect.left,
        offsetY: e.clientY - rect.top,
      };
      panel.classList.add("dragging");
      e.preventDefault();
    });

    window.addEventListener("mousemove", (e) => {
      if (!dragState) return;
      const left = Math.max(8, e.clientX - dragState.offsetX);
      const top = Math.max(8, e.clientY - dragState.offsetY);
      panel.style.left = `${left}px`;
      panel.style.top = `${top}px`;
      panel.style.right = "auto";
      panel.style.bottom = "auto";
      localStorage.setItem("dmportal_student_schedule_pos", JSON.stringify({ left, top }));
    });

    window.addEventListener("mouseup", () => {
      if (!dragState) return;
      dragState = null;
      panel.classList.remove("dragging");
    });
  }

  function openModal() {
    const modal = document.getElementById("slotModal");
    if (!modal) return;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeModal() {
    const modal = document.getElementById("slotModal");
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    setStatusById("modalStatus", "");
  }

  async function updateSlotConflictHint() {
  const conflictEl = document.getElementById("modalConflict");
  if (conflictEl) {
    conflictEl.textContent = "";
    conflictEl.className = "status";
  }

  const doctorId = document.getElementById("modal_doctor_id")?.value;
  const day = document.getElementById("modal_day")?.value;
  const slot = document.getElementById("modal_slot")?.value;
  const courseId = getHiddenCourseId();
  const roomCode = getRoomCodeFromModal();

  if (!doctorId || !day || !slot || !courseId || !state.activeWeekId) return;

  // If the UI already marks it unavailable, block early
  if (isSlotUnavailable(String(day), Number(slot))) {
    setStatusById("modalConflict", "Doctor is unavailable during this slot.", "error");
    return;
  }

  // If the UI already marks it slot-cancelled, block early
  if (state.slotCancellations?.[String(day)]?.[String(slot)] !== undefined) {
    setStatusById("modalConflict", "This slot is cancelled for the doctor.", "error");
    return;
  }

  try {
    const qs = new URLSearchParams({
      doctor_id: String(doctorId),
      week_id: String(state.activeWeekId),
      day_of_week: String(day),
      slot_number: String(slot),
      course_id: String(courseId),
    });

    const roomCode = getRoomCodeFromModal();
    if (roomCode) qs.set("room_code", String(roomCode));

    const payload = await fetchJson(`php/check_slot_conflict.php?${qs.toString()}`);
    if (!payload.success) return;

    const data = payload.data || {};

    if (data.cancelled) {
      setStatusById("modalConflict", "This day is cancelled for the doctor. You cannot schedule here.", "error");
      return;
    }

    if (data.slot_cancelled) {
      setStatusById("modalConflict", "This slot is cancelled for the doctor. You cannot schedule here.", "error");
      return;
    }

    if (data.conflict) {
      const withInfo = data.conflict_with;
      setStatusById(
        "modalConflict",
        `Conflict: ${withInfo.course_name} is already scheduled in this slot with ${withInfo.doctor_name} (same Program/Year/Sem).`,
        "error"
      );
      return;
    }

    if (data.room_conflict) {
      const withInfo = data.room_conflict_with;
      setStatusById(
        "modalConflict",
        `Room conflict: Room ${withInfo.room_code} is already used in this slot by ${withInfo.doctor_name}.`,
        "error"
      );
      return;
    }

    setStatusById("modalConflict", "No conflicts detected.", "success");
  } catch (err) {
    // silent
  }
  }

  async function saveSlotFromModal() {
    const doctorId = document.getElementById("modal_doctor_id")?.value;
    const day = document.getElementById("modal_day")?.value;
    const slot = document.getElementById("modal_slot")?.value;
    const courseId = getHiddenCourseId();

    if (!doctorId || !day || !slot) {
      setStatusById("modalStatus", "Missing slot selection.", "error");
      return;
    }

    if (!courseId) {
      setStatusById("modalStatus", "Please select a course.", "error");
      return;
    }

    setStatusById("modalStatus", "Saving…");

    const fd = new FormData();
    fd.append("doctor_id", doctorId);
    if (state.activeWeekId) fd.append("week_id", String(state.activeWeekId));
    fd.append("day_of_week", day);
    fd.append("slot_number", slot);
    fd.append("course_id", courseId);
    const roomCode = getRoomCodeFromModal();
    if (!roomCode) {
      setStatusById("modalStatus", "Room is required.", "error");
      return;
    }
    if (String(roomCode).length > 50) {
      setStatusById("modalStatus", "Room is too long (max 50 characters).", "error");
      return;
    }

    fd.append("room_code", normalizeSeparator(roomCode));

    const cth = document.getElementById("modal_counts_towards_hours")?.checked ? "1" : "0";
    fd.append("counts_towards_hours", cth);

    const extraSel = document.getElementById("modal_extra_minutes");
    const extraMinutes = extraSel ? String(extraSel.value || "0") : "0";
    fd.append("extra_minutes", extraMinutes);

    try {
      const payload = await fetchJson("php/manage_schedule.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to save slot.");

      await loadSchedule(doctorId);
      renderScheduleGrid();
      updateDoctorExportShareLinks();
      await loadCourses();
      renderCoursesSidebar();
      await loadHoursRemainingPanel();
      closeModal();
      setStatusById("scheduleStatus", "Saved.", "success");
    } catch (err) {
      setStatusById("modalStatus", err.message || "Failed to save slot.", "error");
    }
  }

  async function cancelSlotFromModal() {
    const doctorId = document.getElementById("modal_doctor_id")?.value;
    const day = document.getElementById("modal_day")?.value;
    const slot = document.getElementById("modal_slot")?.value;

    if (!doctorId || !day || !slot || !state.activeWeekId) {
      setStatusById("modalStatus", "Missing slot selection.", "error");
      return;
    }

    setStatusById("modalStatus", "Canceling slot…");
    const reason = document.getElementById("modal_slot_cancel_reason")?.value || "";

    try {
      const fd = new FormData();
      fd.append("week_id", String(state.activeWeekId));
      fd.append("doctor_id", String(doctorId));
      fd.append("day_of_week", String(day));
      fd.append("slot_number", String(slot));
      fd.append("reason", String(reason));

      const payload = await fetchJson("php/set_doctor_slot_cancellation.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to cancel slot");

      await loadSchedule(state.activeDoctorId);
      renderScheduleGrid();

      setStatusById("scheduleStatus", "Slot canceled.", "success");
      setStatusById("modalStatus", "Slot canceled.", "success");
      setStatusById("modalConflict", "This slot is cancelled for the doctor.", "error");
    } catch (err) {
      setStatusById("modalStatus", err.message, "error");
    }
  }

  async function uncancelSlotFromModal() {
    const doctorId = document.getElementById("modal_doctor_id")?.value;
    const day = document.getElementById("modal_day")?.value;
    const slot = document.getElementById("modal_slot")?.value;

    if (!doctorId || !day || !slot || !state.activeWeekId) {
      setStatusById("modalStatus", "Missing slot selection.", "error");
      return;
    }

    setStatusById("modalStatus", "Restoring slot…");

    try {
      const fd = new FormData();
      fd.append("week_id", String(state.activeWeekId));
      fd.append("doctor_id", String(doctorId));
      fd.append("day_of_week", String(day));
      fd.append("slot_number", String(slot));

      const payload = await fetchJson("php/clear_doctor_slot_cancellation.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to restore slot");

      await loadSchedule(state.activeDoctorId);
      renderScheduleGrid();

      setStatusById("scheduleStatus", "Slot restored.", "success");
      setStatusById("modalStatus", "Slot restored.", "success");
      setStatusById("modalConflict", "", "");
    } catch (err) {
      setStatusById("modalStatus", err.message, "error");
    }
  }

  async function removeSlotFromModal() {
    const doctorId = document.getElementById("modal_doctor_id")?.value;
    const day = document.getElementById("modal_day")?.value;
    const slot = document.getElementById("modal_slot")?.value;

    if (!doctorId || !day || !slot) {
      setStatusById("modalStatus", "Missing slot selection.", "error");
      return;
    }

    setStatusById("modalStatus", "Removing…");

    const fd = new FormData();
    fd.append("doctor_id", doctorId);
    if (state.activeWeekId) fd.append("week_id", String(state.activeWeekId));
    fd.append("day_of_week", day);
    fd.append("slot_number", slot);
    fd.append("action", "remove");

    try {
      const payload = await fetchJson("php/manage_schedule.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to remove");

      await loadCourses();
      renderCoursesSidebar();
      await loadHoursRemainingPanel();

      await loadSchedule(state.activeDoctorId);
      renderScheduleGrid();

      setStatusById("scheduleStatus", `Removed (+${SLOT_HOURS}h).`, "success");
      closeModal();
    } catch (err) {
      setStatusById("modalStatus", err.message, "error");
    }
  }

  function openSlotModal(day, slot, assigned) {
  removeBuilderPopup();
  const doctorId = state.activeDoctorId;
  if (!doctorId) return;

  document.getElementById("modal_doctor_id").value = doctorId;
  document.getElementById("modal_day").value = day;
  document.getElementById("modal_slot").value = String(slot);
  document.getElementById("modalSlotLabel").value = `${day} / Slot ${slot}`;

  // IMPORTANT: set selected course first, then populate dropdowns so the selection can be restored.
  setHiddenCourseId(assigned ? String(assigned.course_id) : "");
  populateModalCourses();
  const cid = getHiddenCourseId();
  const codeSel = document.getElementById("modal_course_code");
  const nameSel = document.getElementById("modal_course_name");
  if (codeSel) codeSel.value = cid;
  if (nameSel) nameSel.value = cid;

  const preferredRoomCode = assigned?.room_code
    ? String(assigned.room_code)
    : getConsecutiveRoomCode(day, slot) || getDefaultRoomCodeForCourse(getHiddenCourseId());

  const roomInput = document.getElementById("modal_room_code");
  if (roomInput) roomInput.value = preferredRoomCode ? String(preferredRoomCode) : "";

  // counts towards hours
  const cth = document.getElementById("modal_counts_towards_hours");
  if (cth) cth.checked = assigned ? Boolean(Number(assigned.counts_towards_hours ?? 1)) : true;

  // extra minutes (0/15/30/45)
  const extraSel = document.getElementById("modal_extra_minutes");
  if (extraSel) {
    const v = assigned ? Number(assigned.extra_minutes ?? 0) : 0;
    extraSel.value = [0, 15, 30, 45].includes(v) ? String(v) : "0";
  }

  // prefill cancel reason if slot is already cancelled
  const cancelReason = document.getElementById("modal_slot_cancel_reason");
  if (cancelReason) cancelReason.value = state.slotCancellations?.[day]?.[String(slot)] || "";

  setStatusById("modalConflict", "");

  // If we recently auto-switched Year/Sem for the selected doctor, explain it here.
  try {
    const note = lastBuilderAutoFilterNote;
    if (note && Number(note.doctor_id) === Number(doctorId) && Date.now() - Number(note.at || 0) < 15_000) {
      setStatusById(
        "modalStatus",
        `Filters auto-switched to Year ${note.year_level} / Sem ${note.semester} for this doctor.`,
        "success"
      );
    } else {
      setStatusById("modalStatus", "");
    }
  } catch {
    setStatusById("modalStatus", "");
  }

  openModal();
  // Lazy pre-check conflict if a course is already selected
  updateSlotConflictHint();
  }

  async function loadDoctors() {
  const payload = await fetchJson("php/get_doctors.php");
  if (!payload.success) throw new Error(payload.error || "Failed to load doctors");
  state.doctors = payload.data || [];
  }

  async function loadCourses() {
  const payload = await fetchJson("php/get_courses.php");
  if (!payload.success) throw new Error(payload.error || "Failed to load courses");
  state.courses = payload.data || [];
  }

  async function loadSchedule(doctorId) {
  const qs = new URLSearchParams({ doctor_id: doctorId });
  if (state.activeWeekId) qs.set("week_id", String(state.activeWeekId));

  const payload = await fetchJson(`php/get_schedule.php?${qs.toString()}`);
  if (!payload.success) throw new Error(payload.error || "Failed to load schedule");
  state.scheduleGrid = payload.data?.grid || {};
  state.cancellations = payload.data?.cancellations || {};
  state.slotCancellations = payload.data?.slot_cancellations || {};
  state.unavailability = payload.data?.unavailability || [];
  }

  function updateDoctorExportShareLinks() {
  const emailBtn = document.getElementById("exportDoctorEmail");
  const waBtn = document.getElementById("exportDoctorWhatsApp");

  if (!emailBtn && !waBtn) return;

  const d = (state.doctors || []).find((x) => String(x.doctor_id) === String(state.activeDoctorId));
  const weekLabel = getWeekLabel(state.activeWeekId) || (state.activeWeekId ? `Week ${state.activeWeekId}` : "");

  if (emailBtn) {
    if (d?.email) {
      emailBtn.setAttribute("aria-disabled", "false");

      if (emailBtn.dataset.sendBound !== "1") {
        emailBtn.dataset.sendBound = "1";
        emailBtn.addEventListener("click", async (e) => {
          e.preventDefault();
          e.stopPropagation();

          if (emailBtn.getAttribute("aria-disabled") === "true") {
            return;
          }

          try {
            emailBtn.classList.add("is-loading");
            const payload = await fetchJson("php/email_doctor_schedule.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                doctor_id: state.activeDoctorId,
                week_id: state.activeWeekId || 0,
              }),
            });

            if (payload?.success) {
              alert("Schedule emailed successfully.");
            } else {
              alert(payload?.error || "Failed to send email.");
            }
          } catch (err) {
            alert(err?.message || "Failed to send email.");
          } finally {
            emailBtn.classList.remove("is-loading");
          }
        });
      }
    } else {
      emailBtn.setAttribute("aria-disabled", "true");
    }
  }

  if (waBtn) {
    const p = normalizePhoneForWhatsApp(d?.phone_number);
    if (p) {
      const msg = buildDoctorScheduleGreetingText(d?.full_name);
      waBtn.href = buildWhatsAppSendUrl(p, msg);
      waBtn.setAttribute("aria-disabled", "false");
    } else {
      waBtn.href = "";
      waBtn.setAttribute("aria-disabled", "true");
    }
  }
  }

  function getFilteredCoursesForUI() {
  return applyPageFiltersToCourses(state.courses || []);
  }

  function getDefaultRoomCodeForCourse(courseId) {
  if (!courseId) return "";
  const c = (state.courses || []).find((x) => String(x.course_id) === String(courseId));
  return c?.default_room_code ? String(c.default_room_code) : "";
  }

  function getConsecutiveRoomCode(day, slot) {
  const prevSlot = Number(slot) - 1;
  if (prevSlot < 1) return "";
  const prev = state.scheduleGrid?.[String(day)]?.[String(prevSlot)];
  return prev?.room_code ? String(prev.room_code) : "";
  }

  function getRoomCodeFromModal() {
  return String(document.getElementById("modal_room_code")?.value || "").trim();
  }

  function normalizeSeparator(input) {
  return String(input || "").replace(/\s*[•·\u2022]+\s*/g, " • ");
  }

  async function loadWeeks() {
  const payload = await fetchJson("php/get_weeks.php");
  if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
  state.weeks = payload.data || [];

  // Select active week if present
  const active = state.weeks.find((w) => w.status === "active");
  state.activeWeekId = active ? Number(active.week_id) : null;
  }

  function renderWeeksSelect() {
    const sel = document.getElementById("weekSelect");
    if (!sel) return;

    sel.innerHTML = "";
    if (!state.weeks.length) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "No weeks";
      sel.appendChild(opt);
      return;
    }

    for (const w of state.weeks) {
      const opt = document.createElement("option");
      opt.value = w.week_id;
      const prepTag = Number(w.is_prep || 0) === 1 ? " (prep)" : "";
      const ramadanTag = Number(w.is_ramadan || 0) === 1 ? " (ramadan)" : "";
      opt.textContent = `${w.label}${prepTag}${ramadanTag}${w.status === "active" ? " (active)" : ""}`;
      sel.appendChild(opt);
    }

    if (state.activeWeekId) sel.value = String(state.activeWeekId);
  }

  // -----------------------------------------------------------------------------

  async function initDashboard() {
  try {
    setStatusById("scheduleStatus", "Loading…");

    initPageFiltersUI({ yearSelectId: "builderYearFilterMain", semesterSelectId: "builderSemesterFilterMain" });

    await loadDoctors();
    await loadCourses();
    await loadWeeks();

    renderWeeksSelect();
    renderDoctorsSelect();
    renderCoursesSidebar();
    await loadHoursRemainingPanel();
    initStudentScheduleMini();
    refreshStudentProgramOptions();

    window.addEventListener("dmportal:globalFiltersChanged", () => {
      // Filters affect:
      // - Courses sidebar list
      // - Slot modal course dropdown
      // - Schedule grid (slots from other Year/Sem become "Occupied")
      renderCoursesSidebar();
      renderScheduleMetaHint();
      renderScheduleGrid();
      loadHoursRemainingPanel();

      // Refresh modal course dropdown if open
      if (document.getElementById("slotModal")?.classList.contains("open")) {
        populateModalCourses();
        // Re-check conflicts for the newly filtered course selection (best-effort)
        updateSlotConflictHint();
      }
    });

    // pick first doctor
    if (state.doctors.length > 0) {
      renderScheduleMetaHint();
      await setActiveDoctor(state.doctors[0].doctor_id);
    } else {
      renderScheduleGrid();
      setStatusById("scheduleStatus", "No doctors found.", "error");
    }

    document.getElementById("doctorSelect")?.addEventListener("change", async (e) => {
      const v = e.target.value;
      if (!v) {
        state.activeDoctorId = null;
        renderScheduleMetaHint();
        renderScheduleGrid();
        updateDoctorExportShareLinks();
        return;
      }
      await setActiveDoctor(v);
    });

    // Week select
    document.getElementById("weekSelect")?.addEventListener("change", async (e) => {
      const v = e.target.value;
      state.activeWeekId = v ? Number(v) : null;
      if (state.activeDoctorId) {
        await loadSchedule(state.activeDoctorId);
        await refreshUnavailability();
        await refreshAvailability();
        renderScheduleMetaHint();
        renderScheduleGrid();
        updateDoctorExportShareLinks();
      }
      if (document.getElementById("studentScheduleMini")?.classList.contains("open")) {
        refreshStudentScheduleMini();
      }
    });

    // Start/Stop week
    document.getElementById("startWeekBtn")?.addEventListener("click", async () => {
      const d = document.getElementById("weekStartDate")?.value;
      if (!d) {
        setStatusById("scheduleStatus", "Pick a start date first.", "error");
        return;
      }
      const fd = new FormData();
      fd.append("start_date", d);
      const weekType = document.getElementById("weekTypeSelect")?.value || "ACTIVE";
      fd.append("week_type", weekType);
      await fetchJson("php/start_week.php", { method: "POST", body: fd });
      await loadWeeks();
      renderWeeksSelect();
      if (state.activeDoctorId) {
        await loadSchedule(state.activeDoctorId);
        await refreshUnavailability();
        await refreshAvailability();
        renderScheduleMetaHint();
        renderScheduleGrid();
      }
    });

    document.getElementById("updateWeekTypeBtn")?.addEventListener("click", async () => {
      if (!state.activeWeekId) {
        setStatusById("scheduleStatus", "Pick a week first.", "error");
        return;
      }
      const value = document.getElementById("weekTypeUpdate")?.value || "";
      if (!value) {
        setStatusById("scheduleStatus", "Choose a week type to apply.", "error");
        return;
      }
      try {
        setStatusById("scheduleStatus", "Updating week type...");
        const fd = new FormData();
        fd.append("week_id", String(state.activeWeekId));
        fd.append("week_type", value);
        const payload = await fetchJson("php/set_week_type.php", { method: "POST", body: fd });
        if (!payload?.success) throw new Error(payload?.error || "Failed to update week.");
        document.getElementById("weekTypeUpdate").value = "";
        await loadWeeks();
        renderWeeksSelect();
        if (state.activeDoctorId) {
          await loadSchedule(state.activeDoctorId);
          await refreshUnavailability();
          await refreshAvailability();
          renderScheduleMetaHint();
          renderScheduleGrid();
        }
        setStatusById("scheduleStatus", "Week updated.", "success");
      } catch (err) {
        setStatusById("scheduleStatus", err.message || "Failed to update week.", "error");
      }
    });

    document.getElementById("stopWeekBtn")?.addEventListener("click", async () => {
      await fetchJson("php/stop_week.php", { method: "POST", body: new FormData() });
      await loadWeeks();
      renderWeeksSelect();
    });

    document.getElementById("exportDoctorXls")?.addEventListener("click", () => {
      if (!state.activeDoctorId) return;
      const qs = new URLSearchParams({ doctor_id: String(state.activeDoctorId) });
      if (state.activeWeekId) qs.set("week_id", String(state.activeWeekId));
      window.location.href = `php/export_doctor_week_xls.php?${qs.toString()}`;
    });

    document.getElementById("exportAllDoctorsXls")?.addEventListener("click", () => {
      const qs = new URLSearchParams();
      if (state.activeWeekId) qs.set("week_id", String(state.activeWeekId));
      window.location.href = `php/export_all_doctors_week_xls.php?${qs.toString()}`;
    });

    // Unavailability add/remove
    document.getElementById("addUnavailBtn")?.addEventListener("click", async () => {
      if (!state.activeDoctorId || !state.activeWeekId) {
        setStatusById("unavailStatus", "Select a week and a doctor first.", "error");
        return;
      }

      const start = document.getElementById("unavailStart")?.value;
      const end = document.getElementById("unavailEnd")?.value;
      const reason = document.getElementById("unavailReason")?.value || "";

      if (!start || !end) {
        setStatusById("unavailStatus", "Start and end are required.", "error");
        return;
      }

      try {
        setStatusById("unavailStatus", "Saving…");
        const fd = new FormData();
        fd.append("doctor_id", String(state.activeDoctorId));
        // datetime-local returns YYYY-MM-DDTHH:mm
        fd.append("start_datetime", String(start).replace("T", " ") + ":00");
        fd.append("end_datetime", String(end).replace("T", " ") + ":00");
        fd.append("reason", String(reason));
        await fetchJson("php/add_unavailability.php", { method: "POST", body: fd });
        setStatusById("unavailStatus", "Saved.", "success");
        await refreshUnavailability();
        renderScheduleMetaHint();
        renderScheduleGrid();
      } catch (err) {
        setStatusById("unavailStatus", err.message, "error");
      }
    });

    document.getElementById("unavailList")?.addEventListener("click", async (e) => {
      const btn = e.target?.closest?.("button[data-unavail-del]");
      if (!btn) return;
      const id = btn.dataset.id;
      if (!id) return;

      const ok = confirm("Remove this unavailability block?");
      if (!ok) return;

      try {
        setStatusById("unavailStatus", "Removing…");
        const fd = new FormData();
        fd.append("unavailability_id", String(id));
        await fetchJson("php/delete_unavailability.php", { method: "POST", body: fd });
        setStatusById("unavailStatus", "Removed.", "success");
        await refreshUnavailability();
        renderScheduleMetaHint();
        renderScheduleGrid();
      } catch (err) {
        setStatusById("unavailStatus", err.message, "error");
      }
    });

    // Cancel/uncancel day
    document.getElementById("cancelDayBtn")?.addEventListener("click", async () => {
      if (!state.activeDoctorId || !state.activeWeekId) {
        setStatusById("cancelStatus", "Select a week and a doctor first.", "error");
        return;
      }
      const day = document.getElementById("cancelDaySelect")?.value;
      const reason = document.getElementById("cancelReason")?.value || "";
      const fd = new FormData();
      fd.append("week_id", String(state.activeWeekId));
      fd.append("doctor_id", String(state.activeDoctorId));
      fd.append("day_of_week", day);
      fd.append("reason", reason);
      await fetchJson("php/set_doctor_cancellation.php", { method: "POST", body: fd });
      setStatusById("cancelStatus", "Day canceled.", "success");
      await loadSchedule(state.activeDoctorId);
      renderScheduleMetaHint();
      renderScheduleGrid();
    });

    document.getElementById("uncancelDayBtn")?.addEventListener("click", async () => {
      if (!state.activeDoctorId || !state.activeWeekId) {
        setStatusById("cancelStatus", "Select a week and a doctor first.", "error");
        return;
      }
      const day = document.getElementById("cancelDaySelect")?.value;
      const fd = new FormData();
      fd.append("week_id", String(state.activeWeekId));
      fd.append("doctor_id", String(state.activeDoctorId));
      fd.append("day_of_week", day);
      await fetchJson("php/clear_doctor_cancellation.php", { method: "POST", body: fd });
      setStatusById("cancelStatus", "Day restored.", "success");
      await loadSchedule(state.activeDoctorId);
      renderScheduleMetaHint();
      renderScheduleGrid();
    });

    // Schedule refresh
    document.getElementById("refreshSchedule")?.addEventListener("click", async () => {
      if (!state.activeDoctorId) return;
      await loadSchedule(state.activeDoctorId);
      await refreshAvailability();
      renderScheduleMetaHint();
      renderScheduleGrid();
      await loadHoursRemainingPanel();
    });

    // Modal buttons
    document.getElementById("modalSave")?.addEventListener("click", saveSlotFromModal);
    document.getElementById("modalRemove")?.addEventListener("click", removeSlotFromModal);
    document.getElementById("modalCancelSlot")?.addEventListener("click", cancelSlotFromModal);
    document.getElementById("modalUncancelSlot")?.addEventListener("click", uncancelSlotFromModal);

    // Conflict hint
    async function onCoursePicked(courseId) {
      setHiddenCourseId(courseId);

      const defaultCode = getDefaultRoomCodeForCourse(courseId);
      const roomInput = document.getElementById("modal_room_code");
      if (roomInput && defaultCode) {
        roomInput.value = String(defaultCode);
      }

      updateSlotConflictHint();
    }

    document.getElementById("modal_course_code")?.addEventListener("change", async (e) => {
      const v = e.target?.value || "";
      // Sync other dropdown
      const nameSel = document.getElementById("modal_course_name");
      if (nameSel) nameSel.value = v;
      await onCoursePicked(v);
    });

    document.getElementById("modal_course_name")?.addEventListener("change", async (e) => {
      const v = e.target?.value || "";
      // Sync other dropdown
      const codeSel = document.getElementById("modal_course_code");
      if (codeSel) codeSel.value = v;
      await onCoursePicked(v);
    });

    // Close handlers (backdrop, close, cancel)
    document.querySelectorAll("#slotModal [data-close='1']")?.forEach((el) => {
      el.addEventListener("click", closeModal);
    });

    // Escape to close
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeModal();
    });

    setStatusById("scheduleStatus", "");
  } catch (err) {
    setStatusById("scheduleStatus", err.message, "error");
  }
  }


  window.dmportal = window.dmportal || {};
  window.dmportal.initScheduleBuilder = initDashboard;
})();
