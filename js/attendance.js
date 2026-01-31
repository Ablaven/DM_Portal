(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml, makeCourseLabel } = window.dmportal || {};

  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30 AM–10:00 AM",
    2: "10:10 AM–11:30 AM",
    3: "11:40 AM–1:00 PM",
    4: "1:10 PM–2:40 PM",
    5: "2:50 PM–4:20 PM",
  };

  const attendanceState = { weeks: [], activeWeekId: null };
  let meCache = null;

  async function authFetchMe() {
    try {
      const payload = await fetchJson("php/auth_me.php");
      return payload?.data || null;
    } catch {
      return null;
    }
  }

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    attendanceState.weeks = payload.data || [];
    const active = attendanceState.weeks.find((w) => w.status === "active");
    attendanceState.activeWeekId = active ? Number(active.week_id) : null;
  }

  function openAttendanceModal() {
  const modal = document.getElementById("attendanceModal");
  if (!modal) return;
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
  }

  function closeAttendanceModal() {
  const modal = document.getElementById("attendanceModal");
  if (!modal) return;
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  setStatusById("attendanceModalStatus", "");
  }

  function renderAttendanceGrid(grid, onSlotClick) {
  const body = document.getElementById("attendanceScheduleBody");
  if (!body) return;

  // Match the Student Schedule UI: same days/slots and the same slot header + slot card markup.
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

      const assigned = grid?.[day]?.[String(slot)] || null;
      const items = (assigned?.items || []).filter(Boolean);

      if (!items.length) {
        cell.innerHTML = `<div class="slot-title">—</div><div class="slot-sub">Empty</div>`;
        cell.style.cursor = "default";
      } else {
        cell.classList.add("filled");
        cell.style.cursor = "pointer";

        if (assigned.multiple) {
          const first = items[0];
          // Use the same "Multiple" visual as Student Schedule.
          cell.innerHTML = `<div class="slot-title">Multiple</div><div class="slot-sub">Same slot</div>`;
          if (first?.doctor_color) {
            cell.style.background = first.doctor_color + "22";
            cell.style.borderColor = first.doctor_color + "88";
          }
          cell.addEventListener("click", () => onSlotClick(day, slot, first, items));
        } else {
          const one = items[0];
          const room = one.room_code ? `Room ${escapeHtml(one.room_code)}` : "";
          const line2 = `${escapeHtml(one.doctor_name || "")} &middot; ${escapeHtml(makeCourseLabel(one.course_type, one.subject_code))}${room ? " &middot; " + room : ""}`;
          cell.innerHTML = `<div class="slot-title">${escapeHtml(one.course_name || "")}</div><div class="slot-sub">${line2}</div>`;
          if (one?.doctor_color) {
            cell.style.background = one.doctor_color + "22";
            cell.style.borderColor = one.doctor_color + "88";
          }
          cell.addEventListener("click", () => onSlotClick(day, slot, one, [one]));
        }
      }

      td.appendChild(cell);
      tr.appendChild(td);
    }

    body.appendChild(tr);
  }
  }

  function renderAttendanceModalRows(items, filterText, options = {}) {
  const body = document.getElementById("attendanceModalBody");
  if (!body) return;

  const { isAdmin = false } = options || {};
  const q = String(filterText || "").trim().toLowerCase();
  const filtered = (items || []).filter((s) => {
    if (!q) return true;
    return (
      String(s.full_name || "").toLowerCase().includes(q) ||
      String(s.student_code || "").toLowerCase().includes(q) ||
      String(s.student_id || "").toLowerCase().includes(q)
    );
  });

  if (!filtered.length) {
    body.innerHTML = `<tr><td colspan="3" class="muted" style="padding:14px;">No matching students.</td></tr>`;
    return;
  }

  body.innerHTML = "";

  for (const s of filtered) {
    const tr = document.createElement("tr");
    const status = String(s.attendance_status || "").toUpperCase();
    const isPresent = status === "PRESENT";
    const hasStatus = status === "PRESENT" || status === "ABSENT";
    const isLocked = !isAdmin && (Boolean(s.attendance_locked) || hasStatus);

    // Default is unchecked => Absent
    tr.innerHTML = `
      <td class="muted">${escapeHtml(s.student_code || s.student_id || "")}</td>
      <td class="student-name">${escapeHtml(s.full_name || "")}</td>
      <td class="attendance-cell">
        <label class="chk">
          <input type="checkbox" class="attendance-present" ${isPresent ? "checked" : ""} ${isLocked ? "disabled" : ""} />
        </label>
      </td>
    `;

    if (isLocked) tr.classList.add("attendance-locked");

    tr.dataset.studentId = String(s.student_id);
    body.appendChild(tr);
  }
  }

  async function initAttendancePage() {
  let currentCtx = null; // {schedule_id:number, items:[]}
  const me = meCache || (await authFetchMe());
  const isAdmin = String(me?.role || "").toLowerCase() === "admin";

  try {
    setStatusById("attendanceStatus", "Loading...");

    await loadWeeks();

    // Courses select (for export)
    const courseSel = document.getElementById("attendanceCourseSelect");

    async function refreshAttendanceCoursesDropdown(activeYear) {
      if (!courseSel) return;

      const weekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const weekId = weekIdVal ? Number(weekIdVal) : attendanceState.activeWeekId;

      courseSel.innerHTML = "";
      const opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "Select a course...";
      courseSel.appendChild(opt0);

      if (!weekId) return;

      const qs = new URLSearchParams({ year_level: String(activeYear), week_id: String(weekId) });
      const payload = await fetchJson(`php/get_attendance_courses.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load courses");

      const courses = payload.data || [];
      for (const c of courses) {
        const opt = document.createElement("option");
        opt.value = String(c.course_id);
        // Course list is already filtered to the active year.
        const label = `${c.course_name}${c.semester ? " (S" + c.semester + ")" : ""}`;
        opt.textContent = label;
        courseSel.appendChild(opt);
      }
    }

    // Weeks select
    const weekSel = document.getElementById("attendanceWeekSelect");
    if (weekSel) {
      weekSel.innerHTML = "";
      for (const w of attendanceState.weeks || []) {
        const opt = document.createElement("option");
        opt.value = String(w.week_id);
        opt.textContent = String(w.label || `Week ${w.week_id}`);
        if (w.status === "active") opt.selected = true;
        weekSel.appendChild(opt);
      }
      if (!weekSel.value && attendanceState.weeks?.[0]) weekSel.value = String(attendanceState.weeks[0].week_id);
    }

    // Modal close handlers
    document.getElementById("attendanceModal")?.addEventListener("click", (e) => {
      const t = e.target;
      if (t?.dataset?.close === "1" || t?.closest?.("[data-close='1']")) closeAttendanceModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAttendanceModal();
    });

    // Year tabs (match Student Schedule UI pattern)
    let activeYear = 1;
    document.querySelectorAll(".tabs [data-year]")?.forEach((b) => b.classList.remove("active"));
    document.querySelector(`.tabs [data-year='${activeYear}']`)?.classList.add("active");

    async function refreshAttendanceGrid() {
      setStatusById("attendanceStatus", "Loading...");

      const weekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const weekId = weekIdVal ? Number(weekIdVal) : attendanceState.activeWeekId;

      const qs = new URLSearchParams({ year_level: String(activeYear) });
      if (weekId) qs.set("week_id", String(weekId));

      const payload = await fetchJson(`php/get_attendance_grid.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load attendance grid");

      renderAttendanceGrid(payload.data?.grid || {}, async (_day, _slot, chosen) => {
        try {
          if (!chosen?.schedule_id) {
            setStatusById("attendanceStatus", "No schedule in this slot.", "error");
            return;
          }

          currentCtx = { schedule_id: Number(chosen.schedule_id), items: [] };

          const meta = document.getElementById("attendanceModalMeta");
          if (meta) {
            meta.innerHTML = `<strong>${escapeHtml(chosen.course_name || "")}</strong> &middot; Year ${escapeHtml(chosen.year_level)}${chosen.room_code ? ` &middot; Room ${escapeHtml(chosen.room_code)}` : ""}`;
          }

          openAttendanceModal();
          setStatusById("attendanceModalStatus", "Loading students...");

          const attPayload = await fetchJson(`php/get_attendance.php?schedule_id=${encodeURIComponent(chosen.schedule_id)}`);
          if (!attPayload.success) throw new Error(attPayload.error || "Failed to load attendance");

          currentCtx.items = attPayload.data?.items || [];
          renderAttendanceModalRows(currentCtx.items, "", { isAdmin });
          dirtyStudents.clear();
          setStatusById("attendanceModalStatus", "");

          const searchEl = document.getElementById("attendanceStudentSearch");
          if (searchEl) {
            searchEl.value = "";
            searchEl.oninput = () => renderAttendanceModalRows(currentCtx.items, searchEl.value || "", { isAdmin });
          }
        } catch (err) {
          setStatusById("attendanceModalStatus", err.message || "Failed to load attendance", "error");
        }
      });

      setStatusById("attendanceStatus", "");
    }

    document.getElementById("refreshAttendanceGrid")?.addEventListener("click", async () => {
      await refreshAttendanceGrid();
      await refreshAttendanceCoursesDropdown(activeYear);
    });
    document.getElementById("attendanceWeekSelect")?.addEventListener("change", async () => {
      await refreshAttendanceGrid();
      await refreshAttendanceCoursesDropdown(activeYear);
    });

    document.querySelectorAll(".tabs [data-year]")?.forEach((btn) => {
      btn.addEventListener("click", async () => {
        document.querySelectorAll(".tabs [data-year]").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        activeYear = Number(btn.dataset.year) || 1;
        await refreshAttendanceGrid();
        await refreshAttendanceCoursesDropdown(activeYear);
      });
    });

    const dirtyStudents = new Set();

    async function saveSingle(studentId, status) {
      const fd = new FormData();
      fd.append("schedule_id", String(currentCtx?.schedule_id || 0));
      fd.append("student_id", String(studentId));
      fd.append("status", String(status));
      const payload = await fetchJson("php/set_attendance.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Save failed");
    }

    function markDirty(studentId) {
      if (!studentId) return;
      dirtyStudents.add(studentId);
      setStatusById("attendanceModalStatus", "Unsaved changes", "warn");
    }

    document.getElementById("attendanceModalBody")?.addEventListener("change", (e) => {
      const inp = e.target;
      if (!(inp instanceof HTMLInputElement)) return;
      if (inp.type !== "checkbox" || !inp.classList.contains("attendance-present")) return;

      const tr = inp.closest("tr");
      const studentId = Number(tr?.dataset?.studentId || 0);
      if (!studentId || !currentCtx?.schedule_id) return;

      if (!isAdmin) {
        const target = (currentCtx.items || []).find((x) => Number(x.student_id) === studentId);
        if (target?.attendance_locked) {
          inp.checked = String(target.attendance_status || "").toUpperCase() === "PRESENT";
          return;
        }
      }

      const status = inp.checked ? "PRESENT" : "ABSENT";
      const target = (currentCtx.items || []).find((x) => Number(x.student_id) === studentId);
      if (target) target.attendance_status = status;

      markDirty(studentId);
    });

    async function bulkSet(toStatus) {
      if (!currentCtx?.schedule_id) {
        setStatusById("attendanceModalStatus", "Open a slot first.", "error");
        return;
      }
      const targets = (currentCtx.items || []).map((x) => Number(x.student_id)).filter((n) => n > 0);
      if (!targets.length) {
        setStatusById("attendanceModalStatus", "No students to update.", "error");
        return;
      }

      for (const it of currentCtx.items || []) it.attendance_status = toStatus;
      renderAttendanceModalRows(currentCtx.items, document.getElementById("attendanceStudentSearch")?.value || "", { isAdmin });
      for (const sid of targets) dirtyStudents.add(sid);
      setStatusById("attendanceModalStatus", `Marked ${targets.length}. Remember to save.`, "warn");
    }

    document.getElementById("attendanceMarkAllPresent")?.addEventListener("click", () => bulkSet("PRESENT"));
    document.getElementById("attendanceMarkAllAbsent")?.addEventListener("click", () => bulkSet("ABSENT"));

    if (!isAdmin) {
      const bulkPresent = document.getElementById("attendanceMarkAllPresent");
      const bulkAbsent = document.getElementById("attendanceMarkAllAbsent");
      if (bulkPresent) bulkPresent.style.display = "none";
      if (bulkAbsent) bulkAbsent.style.display = "none";
    }

    document.getElementById("attendanceSaveChanges")?.addEventListener("click", async () => {
      if (!currentCtx?.schedule_id) {
        setStatusById("attendanceModalStatus", "Open a slot first.", "error");
        return;
      }
      const targets = Array.from(dirtyStudents || []).filter((n) => Number(n) > 0);
      if (!targets.length) {
        setStatusById("attendanceModalStatus", "No changes to save.", "info");
        return;
      }

      try {
        setStatusById("attendanceModalStatus", `Saving ${targets.length}...`);
        let done = 0;
        for (const sid of targets) {
          const it = (currentCtx.items || []).find((x) => Number(x.student_id) === sid);
          const status = String(it?.attendance_status || "ABSENT").toUpperCase() === "PRESENT" ? "PRESENT" : "ABSENT";
          await saveSingle(sid, status);
          done++;
          if (done % 10 === 0) setStatusById("attendanceModalStatus", `Saving... ${done}/${targets.length}`);
        }

        dirtyStudents.clear();
        if (!isAdmin) {
          for (const it of currentCtx.items || []) {
            const status = String(it?.attendance_status || "").toUpperCase();
            if (status === "PRESENT" || status === "ABSENT") {
              it.attendance_locked = true;
            }
          }
          renderAttendanceModalRows(currentCtx.items, document.getElementById("attendanceStudentSearch")?.value || "", { isAdmin });
        }
        setStatusById("attendanceModalStatus", `Saved ${targets.length}.`, "success");
      } catch (err) {
        setStatusById("attendanceModalStatus", err.message || "Save failed", "error");
      }
    });

    // Export whole subject (course) with 20-week placeholders
    document.getElementById("exportAttendanceXls")?.addEventListener("click", () => {
      const courseId = Number(document.getElementById("attendanceCourseSelect")?.value || 0);
      if (!courseId) {
        setStatusById("attendanceStatus", "Select a course to export.", "error");
        return;
      }

      const endWeekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const endWeekId = endWeekIdVal ? Number(endWeekIdVal) : 0;
      if (!endWeekId) {
        setStatusById("attendanceStatus", "Select a week.", "error");
        return;
      }

      const qs = new URLSearchParams({ course_id: String(courseId), end_week_id: String(endWeekId) });
      window.location.href = `php/export_attendance_xls.php?${qs.toString()}`;
    });

    await refreshAttendanceGrid();
    await refreshAttendanceCoursesDropdown(activeYear);
  } catch (err) {
    setStatusById("attendanceStatus", err.message || "Failed to load Attendance.", "error");
  }
  }


  window.dmportal = window.dmportal || {};
  window.dmportal.initAttendancePage = initAttendancePage;
})();
