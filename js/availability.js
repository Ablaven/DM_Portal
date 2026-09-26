(function () {
  "use strict";

  const { 
    fetchJson, 
    setStatusById, 
    escapeHtml, 
    formatWeekDisplayLabel,
    showSuccess,
    showError 
  } = window.dmportal || {};

  const DAYS = ["SUN", "MON", "TUE", "WED", "THU"];
  const DAY_LABELS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30–10:00 AM",
    2: "10:10–11:30 AM",
    3: "11:40–1:00 PM",
    4: "1:10–2:40 PM",
    5: "2:50–4:20 PM",
  };

  const availabilityState = {
    doctors: [],
    weeks: [],
    activeWeekId: null,
    activeDoctorId: null,
    activeDoctorName: "",
    availabilityItems: [],
    availabilityMap: {},
    unavailabilityItems: [],
    unavailabilitySlotMap: {},
    role: "teacher",
  };

  // ============================================
  // UTILITY FUNCTIONS
  // ============================================

  function slotLabel(slot) {
    return `Slot ${slot} (${SLOT_TIMES[slot] || ""})`;
  }

  function normalizeDay(day) {
    const d = String(day || "").trim().toUpperCase();
    return DAYS.includes(d) ? d : "";
  }

  function buildAvailabilityMap(items) {
    const map = {};
    for (const item of items || []) {
      const day = normalizeDay(item.day_of_week);
      const slot = String(item.slot_number || "").trim();
      if (!day || !slot) continue;
      if (!map[day]) map[day] = {};
      if (!map[day][slot]) map[day][slot] = [];
      map[day][slot].push(item);
    }
    return map;
  }

  /**
   * Build a map of unavailability periods that overlap with specific day/slot combinations.
   * Returns: { DAY: { SLOT: true/false } }
   */
  function buildUnavailabilitySlotMap(unavailItems, weekStartDate) {
    const map = {};
    
    if (!weekStartDate) return map;

    try {
      const weekStart = new Date(weekStartDate);
      
      for (const unavail of unavailItems || []) {
        const unavailStart = new Date(unavail.start_datetime);
        const unavailEnd = new Date(unavail.end_datetime);
        
        // Check each day of the week
        for (let dayIdx = 0; dayIdx < DAYS.length; dayIdx++) {
          const dayDate = new Date(weekStart);
          dayDate.setDate(weekStart.getDate() + dayIdx);
          
          const day = DAYS[dayIdx];
          
          // Check each slot
          for (const slot of SLOTS) {
            const slotStart = new Date(dayDate);
            const slotEnd = new Date(dayDate);
            
            // Slot times (approximate - using regular schedule times)
            const slotTimes = {
              1: { start: 8.5, end: 10 },      // 8:30 - 10:00
              2: { start: 10.17, end: 11.5 },  // 10:10 - 11:30
              3: { start: 11.67, end: 13 },    // 11:40 - 13:00
              4: { start: 13.17, end: 14.67 }, // 13:10 - 14:40
              5: { start: 14.83, end: 16.33 }  // 14:50 - 16:20
            };
            
            const times = slotTimes[slot];
            if (!times) continue;
            
            slotStart.setHours(Math.floor(times.start), (times.start % 1) * 60, 0, 0);
            slotEnd.setHours(Math.floor(times.end), (times.end % 1) * 60, 0, 0);
            
            // Check if unavailability period overlaps with this slot
            if (unavailStart < slotEnd && unavailEnd > slotStart) {
              if (!map[day]) map[day] = {};
              map[day][slot] = true;
            }
          }
        }
      }
    } catch (e) {
      console.error("Failed to build unavailability slot map:", e);
    }
    
    return map;
  }



  // ============================================
  // GRID RENDERING
  // ============================================

  function renderGrid() {
    const body = document.getElementById("availabilityScheduleBody");
    if (!body) return;

    const showAllDoctors = !availabilityState.activeDoctorId;
    body.innerHTML = "";

    let totalAvailable = 0;

    for (const slot of SLOTS) {
      const tr = document.createElement("tr");
      
      // Time header cell
      const th = document.createElement("th");
      th.innerHTML = `
        <div class="slot-hdr">
          <div class="slot-hdr-num">Slot ${slot}</div>
          <div class="slot-hdr-time">${escapeHtml(SLOT_TIMES[slot] || "")}</div>
        </div>
      `;
      tr.appendChild(th);

      // Day cells
      for (let dayIdx = 0; dayIdx < DAYS.length; dayIdx++) {
        const day = DAYS[dayIdx];
        const td = document.createElement("td");
        const cell = document.createElement("div");
        cell.className = "slot availability-slot";

        const availItems = availabilityState.availabilityMap?.[day]?.[String(slot)] || [];
        const isUnavailable = availabilityState.unavailabilitySlotMap?.[day]?.[slot] || false;

        // Determine slot state
        if (isUnavailable && !showAllDoctors) {
          // Blocked by unavailability period
          cell.classList.add("blocked");
          cell.innerHTML = `
            <div class="slot-status-label">Unavailable</div>
            <div class="slot-status-sub">Blocked by period</div>
          `;
          cell.style.cursor = "not-allowed";
        } else if (availItems.length > 0) {
          // Available
          cell.classList.add("available");
          if (showAllDoctors) {
            cell.classList.add("multi-doctor");
            const names = availItems.map((i) => i.full_name).filter(Boolean).slice(0, 2);
            const suffix = availItems.length > 2 ? ` +${availItems.length - 2}` : "";
            const preview = names.length ? `${escapeHtml(names.join(", "))}${suffix}` : `${availItems.length} doctors`;
            cell.innerHTML = `
              <div class="slot-status-label">${availItems.length} Available</div>
              <div class="slot-status-sub">${preview}</div>
            `;
          } else {
            cell.innerHTML = `
              <div class="slot-status-label">Available</div>
              <div class="slot-status-sub">Click to remove</div>
            `;
          }
          totalAvailable++;
        } else {
          // Unavailable
          cell.classList.add("unavailable");
          cell.innerHTML = `
            <div class="slot-status-label">Not Available</div>
            <div class="slot-status-sub">Click to add</div>
          `;
        }

        cell.addEventListener("click", (e) => handleSlotClick(day, slot, availItems, e));
        td.appendChild(cell);
        tr.appendChild(td);
      }

      body.appendChild(tr);
    }

    updateStats(totalAvailable);
  }

  function updateStats(available) {
    const statsEl = document.getElementById("availabilityStats");
    const total = DAYS.length * SLOTS.length; // 5 days * 5 slots = 25 total
    
    // Count blocked slots
    let blockedCount = 0;
    for (const day of DAYS) {
      for (const slot of SLOTS) {
        if (availabilityState.unavailabilitySlotMap?.[day]?.[slot]) {
          blockedCount++;
        }
      }
    }
    
    const unavailable = total - available - blockedCount;
    const percentage = total > 0 ? Math.round((available / total) * 100) : 0;
    
    // Update summary stats at top
    const statsAvailableEl = document.getElementById("statsAvailableSlots");
    const statsUnavailableEl = document.getElementById("statsUnavailableSlots");
    const statsBlockedEl = document.getElementById("statsBlockedSlots");
    const statsPercentEl = document.getElementById("statsAvailabilityPercent");
    
    if (statsAvailableEl) statsAvailableEl.textContent = available;
    if (statsUnavailableEl) statsUnavailableEl.textContent = unavailable;
    if (statsBlockedEl) statsBlockedEl.textContent = blockedCount;
    if (statsPercentEl) statsPercentEl.textContent = percentage + '%';
    
    // Update inline stats bar
    if (!statsEl) return;

    const showAllDoctors = !availabilityState.activeDoctorId;
    if (showAllDoctors) {
      statsEl.innerHTML = `
        <div class="stat">
          <span class="stat-value">${available}</span>
          <span>Available Slots</span>
        </div>
      `;
    } else {
      statsEl.innerHTML = `
        <div class="stat">
          <span class="stat-value">${available}/${total}</span>
          <span>Available Slots</span>
        </div>
        <div class="stat">
          <span class="stat-value">${percentage}%</span>
          <span>Coverage</span>
        </div>
      `;
    }
  }

  // ============================================
  // SLOT INTERACTION
  // ============================================

  async function handleSlotClick(day, slot, availItems, event) {
    const showAllDoctors = !availabilityState.activeDoctorId;

    // Admin view - show doctor list modal
    if (showAllDoctors) {
      openDoctorsModal(day, slot, availItems);
      return;
    }

    if (!availabilityState.activeWeekId || !availabilityState.activeDoctorId) {
      showError("Please select a week first.");
      return;
    }

    try {
      setStatusById("availabilityStatus", "Saving...");
      const fd = new FormData();
      fd.append("week_id", String(availabilityState.activeWeekId));
      fd.append("doctor_id", String(availabilityState.activeDoctorId));
      fd.append("day_of_week", day);
      fd.append("slot_number", String(slot));
      fd.append("action", availItems.length ? "remove" : "add");

      const payload = await fetchJson("php/set_doctor_availability.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to update availability");

      await refreshAvailability();
      renderGrid();
      setStatusById("availabilityStatus", availItems.length ? "Removed availability" : "Added availability", "success");
    } catch (err) {
      setStatusById("availabilityStatus", err.message, "error");
    }
  }

  // ============================================
  // BULK OPERATIONS
  // ============================================

  async function copyFromPreviousWeek() {
    if (!availabilityState.activeDoctorId) {
      showError("Please select a doctor first.");
      return;
    }

    if (!availabilityState.activeWeekId) {
      showError("Please select a week first.");
      return;
    }

    if (!confirm("Copy availability from the previous week? This will overwrite existing availability for this week.")) {
      return;
    }

    try {
      setStatusById("availabilityStatus", "Copying from previous week...");

      // Find previous week
      const currentIdx = availabilityState.weeks.findIndex(w => Number(w.week_id) === Number(availabilityState.activeWeekId));
      if (currentIdx <= 0) {
        throw new Error("No previous week found.");
      }

      const prevWeek = availabilityState.weeks[currentIdx - 1];
      if (!prevWeek) {
        throw new Error("No previous week found.");
      }

      // Fetch previous week's availability
      const qs = new URLSearchParams({
        week_id: String(prevWeek.week_id),
        doctor_id: String(availabilityState.activeDoctorId)
      });
      const prevPayload = await fetchJson(`php/get_doctor_availability.php?${qs.toString()}`);
      if (!prevPayload.success) throw new Error("Failed to load previous week availability");

      const prevItems = prevPayload.data?.items || [];
      if (prevItems.length === 0) {
        showError("No availability found in the previous week.");
        return;
      }

      // Copy each slot
      let copied = 0;
      for (const item of prevItems) {
        const fd = new FormData();
        fd.append("week_id", String(availabilityState.activeWeekId));
        fd.append("doctor_id", String(availabilityState.activeDoctorId));
        fd.append("day_of_week", String(item.day_of_week));
        fd.append("slot_number", String(item.slot_number));
        fd.append("action", "add");

        const payload = await fetchJson("php/set_doctor_availability.php", { method: "POST", body: fd });
        if (payload.success) copied++;
      }

      await refreshAvailability();
      renderGrid();
      showSuccess(`Copied ${copied} slots from ${formatWeekDisplayLabel(prevWeek)}`);
      setStatusById("availabilityStatus", "");
    } catch (err) {
      setStatusById("availabilityStatus", err.message, "error");
    }
  }

  async function clearWeekAvailability() {
    if (!availabilityState.activeDoctorId) {
      showError("Please select a doctor first.");
      return;
    }

    if (!availabilityState.activeWeekId) {
      showError("Please select a week first.");
      return;
    }

    if (!confirm("Clear all availability for this week? This cannot be undone.")) {
      return;
    }

    try {
      setStatusById("availabilityStatus", "Clearing week...");

      // Remove all available slots
      let cleared = 0;
      for (const day of DAYS) {
        for (const slot of SLOTS) {
          const items = availabilityState.availabilityMap?.[day]?.[String(slot)] || [];
          if (items.length > 0) {
            const fd = new FormData();
            fd.append("week_id", String(availabilityState.activeWeekId));
            fd.append("doctor_id", String(availabilityState.activeDoctorId));
            fd.append("day_of_week", day);
            fd.append("slot_number", String(slot));
            fd.append("action", "remove");

            const payload = await fetchJson("php/set_doctor_availability.php", { method: "POST", body: fd });
            if (payload.success) cleared++;
          }
        }
      }

      await refreshAvailability();
      renderGrid();
      showSuccess(`Cleared ${cleared} slots`);
      setStatusById("availabilityStatus", "");
    } catch (err) {
      setStatusById("availabilityStatus", err.message, "error");
    }
  }

  // ============================================
  // MODAL FUNCTIONS
  // ============================================

  function openDoctorsModal(day, slot, items) {
    const modal = document.getElementById("availabilityDoctorsModal");
    if (!modal) return;

    const dayLabel = DAY_LABELS[DAYS.indexOf(day)] || day;
    const title = document.getElementById("availabilityDoctorsTitle");
    if (title) title.textContent = `${dayLabel} • ${slotLabel(slot)}`;

    const list = document.getElementById("availabilityDoctorsList");
    if (list) {
      if (!items.length) {
        list.innerHTML = `<div class="muted">No doctors available in this slot.</div>`;
      } else {
        list.innerHTML = "";
        for (const item of items) {
          const div = document.createElement("div");
          div.className = "doctor-list-item";
          div.innerHTML = `
            <div class="doctor-icon">👨‍🏫</div>
            <div class="doctor-name">${escapeHtml(item.full_name || "Doctor")}</div>
          `;
          list.appendChild(div);
        }
      }
    }

    modal.style.display = "flex";
    modal.setAttribute("aria-hidden", "false");
  }

  function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = "none";
    modal.setAttribute("aria-hidden", "true");
  }

  // ============================================
  // DATA LOADING
  // ============================================

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    availabilityState.weeks = payload.data || [];
    const active = availabilityState.weeks.find((w) => w.status === "active");
    availabilityState.activeWeekId = active ? Number(active.week_id) : (availabilityState.weeks[0] ? Number(availabilityState.weeks[0].week_id) : null);
  }

  async function loadDoctors() {
    const payload = await fetchJson("php/get_doctors.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load doctors");
    availabilityState.doctors = payload.data || [];
  }

  async function refreshAvailability() {
    if (!availabilityState.activeWeekId) return;

    const qs = new URLSearchParams({ week_id: String(availabilityState.activeWeekId) });
    if (availabilityState.activeDoctorId) {
      qs.set("doctor_id", String(availabilityState.activeDoctorId));
    }

    const payload = await fetchJson(`php/get_doctor_availability.php?${qs.toString()}`);
    if (!payload.success) throw new Error(payload.error || "Failed to load availability");

    availabilityState.availabilityItems = payload.data?.items || [];
    availabilityState.availabilityMap = buildAvailabilityMap(availabilityState.availabilityItems);
  }

  async function loadUnavailability() {
    const list = document.getElementById("unavailabilityList");
    const section = document.getElementById("unavailabilitySection");

    // Hide section if no specific doctor selected
    if (!availabilityState.activeWeekId || !availabilityState.activeDoctorId) {
      if (section) section.style.display = "none";
      if (list) list.innerHTML = `<div class="muted">Select a doctor and week to view unavailability periods.</div>`;
      availabilityState.unavailabilityItems = [];
      availabilityState.unavailabilitySlotMap = {};
      return;
    }

    // Show section
    if (section) section.style.display = "block";

    try {
      const qs = new URLSearchParams({
        week_id: String(availabilityState.activeWeekId),
        doctor_id: String(availabilityState.activeDoctorId)
      });
      const payload = await fetchJson(`php/get_unavailability.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load unavailability");

      availabilityState.unavailabilityItems = payload.data?.items || [];
      
      // Build slot map for visual blocking
      const currentWeek = availabilityState.weeks.find(w => Number(w.week_id) === Number(availabilityState.activeWeekId));
      const weekStartDate = currentWeek?.start_date || null;
      availabilityState.unavailabilitySlotMap = buildUnavailabilitySlotMap(availabilityState.unavailabilityItems, weekStartDate);
      
      renderUnavailabilityList();
    } catch (err) {
      if (list) list.innerHTML = `<div class="muted">Failed to load unavailability periods.</div>`;
      availabilityState.unavailabilityItems = [];
      availabilityState.unavailabilitySlotMap = {};
    }
  }

  function renderUnavailabilityList() {
    const list = document.getElementById("unavailabilityList");
    const section = document.getElementById("unavailabilitySection");
    if (!list) return;

    // Show/hide section based on whether we have a specific doctor selected
    if (section) {
      section.style.display = availabilityState.activeDoctorId ? 'block' : 'none';
    }

    if (availabilityState.unavailabilityItems.length === 0) {
      list.innerHTML = `<div class="muted">No unavailability periods for this week.</div>`;
      return;
    }

    list.innerHTML = "";
    for (const item of availabilityState.unavailabilityItems) {
      const div = document.createElement("div");
      div.className = "unavailability-item";

      const start = new Date(item.start_datetime).toLocaleString();
      const end = new Date(item.end_datetime).toLocaleString();
      const reason = item.reason ? escapeHtml(item.reason) : "<em>No reason provided</em>";

      div.innerHTML = `
        <div class="unavailability-info">
          <div class="unavailability-dates">${escapeHtml(start)} → ${escapeHtml(end)}</div>
          <div class="unavailability-reason">${reason}</div>
        </div>
        <div class="unavailability-actions">
          <button class="btn btn-danger btn-sm" data-id="${item.unavailability_id}">Delete</button>
        </div>
      `;

      const deleteBtn = div.querySelector("button");
      deleteBtn?.addEventListener("click", () => deleteUnavailability(item.unavailability_id));

      list.appendChild(div);
    }
  }

  function openUnavailabilityModal() {
    if (!availabilityState.activeDoctorId) {
      showError("Please select a doctor first.");
      return;
    }

    const modal = document.getElementById("unavailabilityModal");
    if (!modal) return;

    // Reset form
    const form = document.getElementById("unavailabilityForm");
    if (form) form.reset();

    const statusEl = document.getElementById("unavailStatus");
    if (statusEl) {
      statusEl.textContent = "";
      statusEl.className = "status";
    }

    // Set default datetime values (current week)
    const week = availabilityState.weeks.find(w => Number(w.week_id) === Number(availabilityState.activeWeekId));
    if (week && week.start_date) {
      try {
        const startDate = new Date(week.start_date + 'T08:00');
        const endDate = new Date(week.start_date + 'T17:00');
        
        const formatDateTime = (d) => {
          const year = d.getFullYear();
          const month = String(d.getMonth() + 1).padStart(2, '0');
          const day = String(d.getDate()).padStart(2, '0');
          const hours = String(d.getHours()).padStart(2, '0');
          const minutes = String(d.getMinutes()).padStart(2, '0');
          return `${year}-${month}-${day}T${hours}:${minutes}`;
        };

        const startInput = document.getElementById("unavailStartDatetime");
        const endInput = document.getElementById("unavailEndDatetime");
        if (startInput) startInput.value = formatDateTime(startDate);
        if (endInput) endInput.value = formatDateTime(endDate);
      } catch (e) {
        console.error("Failed to set default dates:", e);
      }
    }

    modal.style.display = "flex";
    modal.setAttribute("aria-hidden", "false");
  }

  async function submitUnavailability(e) {
    e.preventDefault();

    if (!availabilityState.activeDoctorId) {
      showError("Please select a doctor first.");
      return;
    }

    const startInput = document.getElementById("unavailStartDatetime");
    const endInput = document.getElementById("unavailEndDatetime");
    const reasonInput = document.getElementById("unavailReason");
    const statusEl = document.getElementById("unavailStatus");

    const startVal = startInput?.value || "";
    const endVal = endInput?.value || "";
    const reasonVal = reasonInput?.value || "";

    if (!startVal || !endVal) {
      if (statusEl) {
        statusEl.textContent = "Please fill in start and end date/time.";
        statusEl.className = "status status-error";
      }
      return;
    }

    try {
      if (statusEl) {
        statusEl.textContent = "Saving...";
        statusEl.className = "status";
      }

      const fd = new FormData();
      fd.append("doctor_id", String(availabilityState.activeDoctorId));
      fd.append("start_datetime", startVal);
      fd.append("end_datetime", endVal);
      fd.append("reason", reasonVal);

      const payload = await fetchJson("php/add_unavailability.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to add unavailability");

      closeModal("unavailabilityModal");
      await loadUnavailability();
      renderGrid(); // Re-render grid to show blocked slots
      showSuccess("Unavailability period added");
    } catch (err) {
      if (statusEl) {
        statusEl.textContent = err.message || "Failed to add unavailability";
        statusEl.className = "status status-error";
      }
    }
  }

  async function deleteUnavailability(id) {
    if (!confirm("Delete this unavailability period?")) return;

    try {
      const fd = new FormData();
      fd.append("unavailability_id", String(id));

      const payload = await fetchJson("php/delete_unavailability.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to delete");

      await loadUnavailability();
      renderGrid(); // Re-render grid to update blocked slots
      showSuccess("Unavailability period deleted");
    } catch (err) {
      showError(err.message);
    }
  }

  // ============================================
  // SELECTORS & PAGE COPY
  // ============================================

  function renderSelectors(role) {
    const weekSel = document.getElementById("availabilityWeekSelect");
    if (weekSel) {
      weekSel.innerHTML = "";
      for (const w of availabilityState.weeks) {
        const opt = document.createElement("option");
        opt.value = w.week_id;
        opt.textContent = formatWeekDisplayLabel ? formatWeekDisplayLabel(w) : String(w.label || `Week ${w.week_id}`);
        weekSel.appendChild(opt);
      }
      if (availabilityState.activeWeekId) weekSel.value = String(availabilityState.activeWeekId);
    }

    const doctorSel = document.getElementById("availabilityDoctorSelect");
    const doctorNameInput = document.getElementById("availabilityDoctorName");

    if (role === "teacher") {
      // Teacher view - read-only doctor name
      if (doctorNameInput && availabilityState.activeDoctorName) {
        doctorNameInput.value = availabilityState.activeDoctorName;
      }
    } else {
      // Admin/Management view - doctor selector
      if (doctorSel) {
        doctorSel.innerHTML = '<option value="">All Doctors</option>';
        for (const d of availabilityState.doctors) {
          const opt = document.createElement("option");
          opt.value = d.doctor_id;
          opt.textContent = d.full_name;
          doctorSel.appendChild(opt);
        }
        if (availabilityState.activeDoctorId) {
          doctorSel.value = String(availabilityState.activeDoctorId);
        }
      }
    }

    updatePageCopy(role);
  }

  function updatePageCopy(role) {
    const title = document.getElementById("availabilityTitle");
    const subtitle = document.getElementById("availabilitySubtitle");

    const doctorName = availabilityState.activeDoctorName || "";
    if (role === "teacher") {
      if (title) title.textContent = "My Availability";
      if (subtitle) subtitle.textContent = "Set your available teaching slots for the active week";
      return;
    }

    if (!availabilityState.activeDoctorId) {
      if (title) title.textContent = "Doctor Availability Overview";
      if (subtitle) subtitle.textContent = "All doctors availability for the selected week. Click a slot to view details.";
      return;
    }

    if (title) title.textContent = doctorName ? `${doctorName} - Availability` : "Doctor Availability";
    if (subtitle) subtitle.textContent = "Manage availability for the selected week";
  }

  // ============================================
  // EVENT BINDING
  // ============================================

  function bindEvents(role) {
    // Week selector
    const weekSel = document.getElementById("availabilityWeekSelect");
    weekSel?.addEventListener("change", async () => {
      availabilityState.activeWeekId = weekSel.value ? Number(weekSel.value) : null;
      setStatusById("availabilityStatus", "Loading...");
      await refreshAvailability();
      if (role === "admin" || role === "management") {
        await loadUnavailability();
      }
      renderGrid();
      setStatusById("availabilityStatus", "");
    });

    // Doctor selector (admin only)
    const doctorSel = document.getElementById("availabilityDoctorSelect");
    if (doctorSel && (role === "admin" || role === "management")) {
      doctorSel.addEventListener("change", async () => {
        availabilityState.activeDoctorId = doctorSel.value ? Number(doctorSel.value) : null;
        const active = availabilityState.doctors.find((d) => String(d.doctor_id) === String(availabilityState.activeDoctorId));
        availabilityState.activeDoctorName = active ? String(active.full_name || "") : "";
        updatePageCopy(role);
        setStatusById("availabilityStatus", "Loading...");
        await refreshAvailability();
        await loadUnavailability();
        renderGrid();
        setStatusById("availabilityStatus", "");
      });
    }

    // Bulk action buttons
    const copyBtn = document.getElementById("copyAvailabilityBtn");
    copyBtn?.addEventListener("click", copyFromPreviousWeek);

    const clearBtn = document.getElementById("clearAvailabilityBtn");
    clearBtn?.addEventListener("click", clearWeekAvailability);

    // Unavailability button (admin/management only)
    const unavailBtn = document.getElementById("addUnavailabilityBtn");
    unavailBtn?.addEventListener("click", openUnavailabilityModal);

    // Unavailability form
    const unavailForm = document.getElementById("unavailabilityForm");
    unavailForm?.addEventListener("submit", submitUnavailability);

    // Modal close handlers
    document.querySelectorAll("[data-close='modal']").forEach(el => {
      el.addEventListener("click", (e) => {
        const modal = e.target.closest(".modal-overlay");
        if (modal) closeModal(modal.id);
      });
    });

    // Escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal("availabilityDoctorsModal");
        closeModal("unavailabilityModal");
      }
    });
  }

  // ============================================
  // INITIALIZATION
  // ============================================

  async function initAvailabilityView({ doctorId, role }) {
    try {
      availabilityState.role = role;
      setStatusById("availabilityStatus", "Loading...");

      await loadWeeks();
      await loadDoctors();

      availabilityState.activeDoctorId = doctorId ? Number(doctorId) : null;
      if (role === "teacher" && !availabilityState.activeDoctorId && availabilityState.doctors.length > 0) {
        availabilityState.activeDoctorId = Number(availabilityState.doctors[0].doctor_id);
      }

      const active = availabilityState.doctors.find((d) => Number(d.doctor_id) === Number(availabilityState.activeDoctorId));
      availabilityState.activeDoctorName = active ? String(active.full_name || "") : "";

      renderSelectors(role);
      await refreshAvailability();
      if (role === "admin" || role === "management") {
        await loadUnavailability();
      }
      renderGrid();
      bindEvents(role);

      setStatusById("availabilityStatus", "");
    } catch (err) {
      setStatusById("availabilityStatus", err.message, "error");
    }
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAvailabilityView = initAvailabilityView;
})();
