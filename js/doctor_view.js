(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    makeCourseLabel,
    getGlobalFilters,
    applyGlobalFiltersToCourses,
    buildMailtoHref,
    buildDoctorScheduleGreetingText,
    buildDoctorScheduleExportUrl,
    normalizePhoneForWhatsApp,
    buildWhatsAppSendUrl,
    triggerBackgroundDownload,
    initPageFiltersUI,
    doesItemMatchGlobalFilters,
  } = window.dmportal || {};

  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30 AM–10:00 AM",
    2: "10:10 AM–11:30 AM",
    3: "11:40 AM–1:00 PM",
    4: "1:10 PM–2:40 PM",
    5: "2:50 PM–4:20 PM",
  };

  const doctorState = {
    doctors: [],
    weeks: [],
    activeWeekId: null,
    scheduleGrid: {},
    cancellations: {},
    slotCancellations: {},
    unavailability: [],
  };

  function slotLabel(slot) {
    const t = SLOT_TIMES[slot] || "";
    return t ? `Slot ${slot} • ${t}` : `Slot ${slot}`;
  }

  function parseLocalDateTime(s) {
    if (!s) return null;
    const iso = String(s).includes("T") ? String(s) : String(s).replace(" ", "T");
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  function slotDateRange(day, slot) {
    const week = (doctorState.weeks || []).find((w) => String(w.week_id) === String(doctorState.activeWeekId));
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
    for (const u of doctorState.unavailability || []) {
      const us = parseLocalDateTime(u.start_datetime);
      const ue = parseLocalDateTime(u.end_datetime);
      if (!us || !ue) continue;
      if (us < r.end && ue > r.start) return true;
    }
    return false;
  }

  async function loadDoctors() {
    const payload = await fetchJson("php/get_doctors.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load doctors");
    doctorState.doctors = payload.data || [];
  }

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    doctorState.weeks = payload.data || [];
    const active = doctorState.weeks.find((w) => w.status === "active");
    doctorState.activeWeekId = active ? Number(active.week_id) : null;
  }

  async function loadSchedule(doctorId) {
    const qs = new URLSearchParams({ doctor_id: doctorId });
    if (doctorState.activeWeekId) qs.set("week_id", String(doctorState.activeWeekId));

    const payload = await fetchJson(`php/get_schedule.php?${qs.toString()}`);
    if (!payload.success) throw new Error(payload.error || "Failed to load schedule");
    doctorState.scheduleGrid = payload.data?.grid || {};
    doctorState.cancellations = payload.data?.cancellations || {};
    doctorState.slotCancellations = payload.data?.slot_cancellations || {};
    doctorState.unavailability = payload.data?.unavailability || [];
  }

  function renderReadOnlyDoctorSchedule(targetBodyId) {
    const body = document.getElementById(targetBodyId);
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
        cell.style.cursor = "default";

        if (doctorState.cancellations?.[day] !== undefined) {
          const reason = doctorState.cancellations[day];
          cell.classList.add("filled");
          cell.style.background = "#99999922";
          cell.style.borderColor = "#99999988";
          cell.innerHTML = `
            <div class="slot-title">Canceled</div>
            <div class="slot-sub">${reason ? reason : "Doctor not coming"}</div>
          `;
          td.appendChild(cell);
          tr.appendChild(td);
          continue;
        }

        if (doctorState.slotCancellations?.[day]?.[String(slot)] !== undefined) {
          const reason = doctorState.slotCancellations[day][String(slot)];
          cell.classList.add("filled");
          cell.style.background = "#99999922";
          cell.style.borderColor = "#99999988";
          cell.innerHTML = `
            <div class="slot-title">Canceled</div>
            <div class="slot-sub">${reason ? reason : "Slot canceled"}</div>
          `;
          td.appendChild(cell);
          tr.appendChild(td);
          continue;
        }

        if (isSlotUnavailable(day, slot)) {
          cell.classList.add("filled");
          cell.classList.add("type-unavail");
          cell.innerHTML = `
            <div class="slot-title">Unavailable</div>
            <div class="slot-sub">Blocked</div>
          `;
          td.appendChild(cell);
          tr.appendChild(td);
          continue;
        }

        const assigned = doctorState.scheduleGrid?.[day]?.[String(slot)];
        const assignedMatchesFilters = assigned ? doesItemMatchGlobalFilters(assigned) : true;
        if (assigned) {
          cell.classList.add("filled");

          if (!assignedMatchesFilters) {
            cell.style.background = "#99999922";
            cell.style.borderColor = "#99999988";
            cell.innerHTML = `
              <div class="slot-title">Occupied</div>
              <div class="slot-sub">Other Year/Sem course</div>
            `;
            td.appendChild(cell);
            tr.appendChild(td);
            continue;
          }
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
            <div class="slot-title">—</div>
            <div class="slot-sub">Empty</div>
          `;
        }

        td.appendChild(cell);
        tr.appendChild(td);
      }

      body.appendChild(tr);
    }
  }

  function renderDoctorCoursesList(courses) {
    const list = document.getElementById("doctorCoursesList");
    if (!list) return;

    const filtered = applyGlobalFiltersToCourses(courses || []);

    if (!filtered.length) {
      const hasAny = (courses || []).length > 0;
      list.innerHTML = hasAny
        ? `<div class="muted">No courses for the selected Year/Sem filters.</div>`
        : `<div class="muted">No courses assigned to this doctor.</div>`;
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
      badge.textContent = `${formatHours(c.remaining_hours)}h left`;

      top.appendChild(left);
      top.appendChild(badge);

      item.appendChild(top);
      list.appendChild(item);
    }
  }

  function formatHours(n) {
    const num = Number(n);
    if (Number.isNaN(num)) return "0.00";
    return num.toFixed(2);
  }

  async function initDoctorView(doctorId) {
    try {
      setStatusById("doctorStatus", "Loading…");
      setStatusById("doctorCoursesStatus", "Loading…");

      initPageFiltersUI({ yearSelectId: "doctorYearFilter", semesterSelectId: "doctorSemesterFilter" });

      await loadDoctors();
      await loadWeeks();

      const d = doctorState.doctors.find((x) => String(x.doctor_id) === String(doctorId));
      if (d) {
        const nameEl = document.getElementById("doctorName");
        if (nameEl) nameEl.textContent = `${d.full_name} — Schedule`;

        const emailBtn = document.getElementById("doctorEmail");
        if (emailBtn) {
          emailBtn.removeAttribute("href");
          emailBtn.setAttribute("aria-disabled", "false");

          if (emailBtn.dataset.sendBound !== "1") {
            emailBtn.dataset.sendBound = "1";
            emailBtn.addEventListener("click", async (e) => {
              e.preventDefault();
              e.stopPropagation();

              try {
                emailBtn.classList.add("is-loading");
                const payload = await fetchJson("php/email_doctor_schedule.php", {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    doctor_id: d.doctor_id,
                    week_id: doctorState.activeWeekId || 0,
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
        }

        const waBtn = document.getElementById("doctorWhatsApp");
        if (waBtn) {
          const p = normalizePhoneForWhatsApp(d.phone_number);
          const href = p ? buildWhatsAppSendUrl(p, buildDoctorScheduleGreetingText(d.full_name)) : "";
          if (href) {
            waBtn.href = href;
            waBtn.setAttribute("aria-disabled", "false");
          } else {
            waBtn.href = "";
            waBtn.setAttribute("aria-disabled", "true");
          }
        }
      }

      const weekSel = document.getElementById("doctorWeekSelect");
      if (weekSel) {
        weekSel.innerHTML = "";
        for (const w of doctorState.weeks) {
          const opt = document.createElement("option");
          opt.value = w.week_id;
          const prepTag = Number(w.is_prep || 0) === 1 ? " (prep)" : "";
          opt.textContent = `${w.label}${prepTag}${w.status === "active" ? " (active)" : ""}`;
          weekSel.appendChild(opt);
        }
        if (doctorState.activeWeekId) weekSel.value = String(doctorState.activeWeekId);

        weekSel.addEventListener("change", async () => {
          const v = weekSel.value;
          doctorState.activeWeekId = v ? Number(v) : null;
          await loadSchedule(doctorId);
          renderReadOnlyDoctorSchedule("doctorScheduleBody");
        });
      }

      const exportBtn = document.getElementById("exportDoctorXls");
      if (exportBtn && exportBtn.dataset.bound !== "1") {
        exportBtn.dataset.bound = "1";
        exportBtn.addEventListener("click", () => {
          const exportUrl = buildDoctorScheduleExportUrl(doctorId, doctorState.activeWeekId);
          if (!exportUrl) {
            setStatusById("doctorStatus", "Select a doctor and week first.", "error");
            return;
          }
          triggerBackgroundDownload(exportUrl);
        });
      }

      await loadSchedule(doctorId);
      renderReadOnlyDoctorSchedule("doctorScheduleBody");
      setStatusById("doctorStatus", "");

      const coursesPayload = await fetchJson(`php/get_doctor_courses.php?doctor_id=${encodeURIComponent(doctorId)}`);
      if (!coursesPayload.success) throw new Error(coursesPayload.error || "Failed to load doctor courses");
      const doctorCourses = coursesPayload.data?.courses || [];
      renderDoctorCoursesList(doctorCourses);

      window.addEventListener("dmportal:globalFiltersChanged", () => {
        renderDoctorCoursesList(doctorCourses);
      });

      setStatusById("doctorCoursesStatus", "");
    } catch (err) {
      setStatusById("doctorStatus", err.message, "error");
      setStatusById("doctorCoursesStatus", err.message, "error");
    }
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initDoctorView = initDoctorView;
})();
