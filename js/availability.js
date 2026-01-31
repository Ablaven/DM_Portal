(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};

  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30 AM–10:00 AM",
    2: "10:10 AM–11:30 AM",
    3: "11:40 AM–1:00 PM",
    4: "1:10 PM–2:40 PM",
    5: "2:50 PM–4:20 PM",
  };

  const availabilityState = {
    doctors: [],
    weeks: [],
    activeWeekId: null,
    activeDoctorId: null,
    activeDoctorName: "",
    availabilityItems: [],
    availabilityMap: {},
    lastPopup: null,
  };

  function slotLabel(slot) {
    const t = SLOT_TIMES[slot] || "";
    return t ? `Slot ${slot} • ${t}` : `Slot ${slot}`;
  }

  function normalizeDay(day) {
    const d = String(day || "").trim().toUpperCase();
    const map = { SUN: "Sun", MON: "Mon", TUE: "Tue", WED: "Wed", THU: "Thu" };
    return map[d] || "";
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

  function renderGrid() {
    const body = document.getElementById("availabilityScheduleBody");
    if (!body) return;

    const showAllDoctors = !availabilityState.activeDoctorId;
    body.innerHTML = "";

    for (const slot of SLOTS) {
      const tr = document.createElement("tr");
      const th = document.createElement("th");
      th.innerHTML = `<div class="slot-hdr"><div class="slot-hdr-num">Slot ${slot}</div><div class="slot-hdr-time">${escapeHtml(SLOT_TIMES[slot] || "")}</div></div>`;
      tr.appendChild(th);

      for (const day of DAYS) {
        const td = document.createElement("td");
        const cell = document.createElement("div");
        cell.className = "slot availability-slot";

        const items = availabilityState.availabilityMap?.[day]?.[String(slot)] || [];
        if (items.length) {
          cell.classList.add("filled", "available-slot");
          if (showAllDoctors) {
            const names = items.map((i) => i.full_name).filter(Boolean).slice(0, 3);
            const suffix = items.length > 3 ? ` +${items.length - 3}` : "";
            const preview = names.length ? `${escapeHtml(names.join(", "))}${suffix}` : `${items.length} doctors`;
            cell.innerHTML = `<div class="slot-title">${items.length} Available</div><div class="slot-sub">${preview}</div>`;
          } else {
            cell.innerHTML = `<div class="slot-title">Available</div><div class="slot-sub">Click to remove</div>`;
          }
        } else {
          cell.innerHTML = `<div class="slot-title">—</div><div class="slot-sub">Click to add</div>`;
        }

        cell.addEventListener("click", (e) => handleSlotClick(day, slot, items, e));
        td.appendChild(cell);
        tr.appendChild(td);
      }

      body.appendChild(tr);
    }
  }

  function openDoctorsModal(day, slot, items) {
    const modal = document.getElementById("availabilityDoctorsModal");
    if (!modal) return;

    const title = document.getElementById("availabilityDoctorsTitle");
    if (title) title.textContent = `${day} • ${slotLabel(slot)}`;

    const list = document.getElementById("availabilityDoctorsList");
    if (list) {
      if (!items.length) {
        list.innerHTML = `<div class="muted">No doctors available in this slot.</div>`;
      } else {
        list.innerHTML = "";
        for (const item of items) {
          const div = document.createElement("div");
          div.className = "course-item";
          div.innerHTML = `<div class="course-top"><div><strong>${escapeHtml(item.full_name || "Doctor")}</strong></div></div>`;
          list.appendChild(div);
        }
      }
    }

    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeDoctorsModal() {
    const modal = document.getElementById("availabilityDoctorsModal");
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  function renderFloatingPopup(day, slot, items, anchor) {
    if (!anchor || !items?.length) return;

    const existing = availabilityState.lastPopup;
    if (existing) {
      existing.remove();
      availabilityState.lastPopup = null;
    }

    const popup = document.createElement("div");
    popup.className = "availability-popup";
    const names = items.map((i) => i.full_name).filter(Boolean);
    const list = names.length ? names.join(" • ") : "Doctors available";
    popup.innerHTML = `<div class="availability-popup-title">Available Doctors</div><div class="availability-popup-body">${escapeHtml(list)}</div>`;

    document.body.appendChild(popup);
    availabilityState.lastPopup = popup;

    const rect = anchor.getBoundingClientRect();
    const left = rect.left + window.scrollX;
    const top = rect.bottom + window.scrollY + 8;
    popup.style.left = `${left}px`;
    popup.style.top = `${top}px`;

    window.setTimeout(() => {
      popup.classList.add("open");
    }, 10);

    const close = () => {
      popup.remove();
      availabilityState.lastPopup = null;
      document.removeEventListener("click", close);
    };

    window.setTimeout(() => {
      document.addEventListener("click", close, { once: true });
    }, 0);
  }

  async function handleSlotClick(day, slot, items, event) {
    const showAllDoctors = !availabilityState.activeDoctorId;
    if (showAllDoctors) {
      openDoctorsModal(day, slot, items);
      renderFloatingPopup(day, slot, items, event?.currentTarget);
      return;
    }

    if (!availabilityState.activeWeekId || !availabilityState.activeDoctorId) {
      setStatusById("availabilityStatus", "Select a week and doctor first.", "error");
      return;
    }

    try {
      setStatusById("availabilityStatus", "Saving…");
      const fd = new FormData();
      fd.append("week_id", String(availabilityState.activeWeekId));
      fd.append("doctor_id", String(availabilityState.activeDoctorId));
      fd.append("day_of_week", String(day));
      fd.append("slot_number", String(slot));
      fd.append("action", items.length ? "remove" : "add");

      const payload = await fetchJson("php/set_doctor_availability.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to update availability");

      await refreshAvailability();
      renderGrid();
      setStatusById("availabilityStatus", "Saved.", "success");
    } catch (err) {
      setStatusById("availabilityStatus", err.message, "error");
    }
  }

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    availabilityState.weeks = payload.data || [];
    const active = availabilityState.weeks.find((w) => w.status === "active");
    availabilityState.activeWeekId = active ? Number(active.week_id) : null;
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

  function renderSelectors(role) {
    const weekSel = document.getElementById("availabilityWeekSelect");
    if (weekSel) {
      weekSel.innerHTML = "";
      for (const w of availabilityState.weeks) {
        const opt = document.createElement("option");
        opt.value = w.week_id;
        opt.textContent = `${w.label}${w.status === "active" ? " (active)" : ""}`;
        weekSel.appendChild(opt);
      }
      if (availabilityState.activeWeekId) weekSel.value = String(availabilityState.activeWeekId);
    }

    const doctorSel = document.getElementById("availabilityDoctorSelect");
    if (doctorSel) {
      doctorSel.innerHTML = "";

      if (role === "admin" || role === "management") {
        const optAll = document.createElement("option");
        optAll.value = "";
        optAll.textContent = "All Doctors";
        doctorSel.appendChild(optAll);
      }

      for (const d of availabilityState.doctors) {
        const opt = document.createElement("option");
        opt.value = d.doctor_id;
        opt.textContent = d.full_name;
        doctorSel.appendChild(opt);
      }

      if (availabilityState.activeDoctorId) {
        doctorSel.value = String(availabilityState.activeDoctorId);
      } else if (role === "teacher") {
        doctorSel.value = String(availabilityState.activeDoctorId || "");
      }

      updatePageCopy(role);
    }
  }

  function updatePageCopy(role) {
    const title = document.getElementById("availabilityTitle");
    const subtitle = document.getElementById("availabilitySubtitle");

    const doctorName = availabilityState.activeDoctorName || "";
    if (role === "teacher") {
      if (title) title.textContent = "My Availability";
      if (subtitle) subtitle.textContent = "Select slots where you are available each week.";
      return;
    }

    if (!availabilityState.activeDoctorId) {
      if (title) title.textContent = "Doctor Availability";
      if (subtitle) subtitle.textContent = "All doctors availability overview. Click a slot to view doctors.";
      return;
    }

    if (title) title.textContent = doctorName ? `${doctorName} — Availability` : "Doctor Availability";
    if (subtitle) subtitle.textContent = "Click a slot to toggle availability for this doctor.";
  }

  function bindEvents(role) {
    const weekSel = document.getElementById("availabilityWeekSelect");
    weekSel?.addEventListener("change", async () => {
      availabilityState.activeWeekId = weekSel.value ? Number(weekSel.value) : null;
      await refreshAvailability();
      renderGrid();
    });

    const doctorSel = document.getElementById("availabilityDoctorSelect");
    if (doctorSel) {
      if (role === "teacher") {
        doctorSel.disabled = true;
      }
      doctorSel.addEventListener("change", async () => {
        availabilityState.activeDoctorId = doctorSel.value ? Number(doctorSel.value) : null;
        const active = availabilityState.doctors.find((d) => String(d.doctor_id) === String(availabilityState.activeDoctorId));
        availabilityState.activeDoctorName = active ? String(active.full_name || "") : "";
        updatePageCopy(role);
        await refreshAvailability();
        renderGrid();
      });
    }

    document.querySelectorAll("#availabilityDoctorsModal [data-close='1']")?.forEach((el) => {
      el.addEventListener("click", closeDoctorsModal);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeDoctorsModal();
    });
  }

  async function initAvailabilityView({ doctorId, role }) {
    try {
      setStatusById("availabilityStatus", "Loading…");

      await loadWeeks();
      await loadDoctors();

      availabilityState.activeDoctorId = doctorId ? Number(doctorId) : null;
      if (role === "teacher" && !availabilityState.activeDoctorId) {
        availabilityState.activeDoctorId = Number(availabilityState.doctors?.[0]?.doctor_id || 0);
      }

      const active = availabilityState.doctors.find((d) => String(d.doctor_id) === String(availabilityState.activeDoctorId));
      availabilityState.activeDoctorName = active ? String(active.full_name || "") : "";

      renderSelectors(role);
      await refreshAvailability();
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
