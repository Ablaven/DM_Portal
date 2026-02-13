(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml, makeCourseLabel, getGlobalFilters, setGlobalFilters, initPageFiltersUI } = window.dmportal || {};

  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30 AM–10:00 AM",
    2: "10:10 AM–11:30 AM",
    3: "11:40 AM–1:00 PM",
    4: "1:10 PM–2:40 PM",
    5: "2:50 PM–4:20 PM",
  };

  const studentState = { weeks: [], activeWeekId: null };

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    studentState.weeks = payload.data || [];
    const active = studentState.weeks.find((w) => w.status === "active");
    studentState.activeWeekId = active ? Number(active.week_id) : null;
  }

  function renderStudentGrid(grid) {
    const body = document.getElementById("studentScheduleBody");
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

        const assigned = grid?.[day]?.[String(slot)];
        if (assigned) {
          cell.classList.add("filled");
          if (assigned.doctor_color) {
            cell.style.background = assigned.doctor_color + "22";
            cell.style.borderColor = assigned.doctor_color + "88";
          }
          if (assigned.kind === "multiple") {
            cell.innerHTML = `<div class="slot-title">Multiple</div><div class="slot-sub">Same slot</div>`;
          } else {
            const room = assigned.room_code ? `Room ${escapeHtml(assigned.room_code)}` : "";
            const line2 = `${escapeHtml(assigned.doctor_name)} • ${escapeHtml(makeCourseLabel(assigned.course_type, assigned.subject_code))}${room ? " • " + room : ""}`;
            cell.innerHTML = `<div class="slot-title">${escapeHtml(assigned.course_name)}</div><div class="slot-sub">${line2}</div>`;
          }
        } else {
          cell.innerHTML = `<div class="slot-title">—</div><div class="slot-sub">Empty</div>`;
        }

        td.appendChild(cell);
        tr.appendChild(td);
      }

      body.appendChild(tr);
    }
  }

  async function initStudentView() {
    try {
      setStatusById("studentStatus", "Loading…");

      await loadWeeks();
      const weekSel = document.getElementById("studentWeekSelect");
      if (weekSel) {
        weekSel.innerHTML = "";
        for (const w of studentState.weeks) {
          const opt = document.createElement("option");
          opt.value = w.week_id;
          const prepTag = Number(w.is_prep || 0) === 1 ? " (prep)" : "";
          opt.textContent = `${w.label}${prepTag}${w.status === "active" ? " (active)" : ""}`;
          weekSel.appendChild(opt);
        }
        if (studentState.activeWeekId) weekSel.value = String(studentState.activeWeekId);
      }

      const gf = getGlobalFilters();
      let activeYear = gf.year_level || 1;
      const semSelect = document.getElementById("studentSemester");
      if (semSelect) {
        semSelect.value = gf.semester ? String(gf.semester) : (semSelect.value || "1");
      }

      document.querySelectorAll(".tabs [data-year]")?.forEach((b) => b.classList.remove("active"));
      document.querySelector(`.tabs [data-year='${activeYear}']`)?.classList.add("active");

      async function refreshStudentSchedule() {
        const program = document.getElementById("studentProgram")?.value || "Digital Marketing";
        const weekIdVal = document.getElementById("studentWeekSelect")?.value;
        const weekId = weekIdVal ? Number(weekIdVal) : studentState.activeWeekId;

        const semester = document.getElementById("studentSemester")?.value || "1";
        const qs = new URLSearchParams({ program, year_level: String(activeYear), semester: String(semester) });
        if (weekId) qs.set("week_id", String(weekId));

        const payload = await fetchJson(`php/get_student_schedule.php?${qs.toString()}`);
        if (!payload.success) throw new Error(payload.error || "Failed to load student schedule");

        renderStudentGrid(payload.data?.grid || {});
        setStatusById("studentStatus", "");
      }

      document.getElementById("studentProgram")?.addEventListener("change", refreshStudentSchedule);
      document.getElementById("studentSemester")?.addEventListener("change", async () => {
        const sem = Number(document.getElementById("studentSemester")?.value || 0);
        const next = getGlobalFilters();
        next.semester = sem || 0;
        setGlobalFilters(next);
        await refreshStudentSchedule();
      });
      document.getElementById("studentWeekSelect")?.addEventListener("change", refreshStudentSchedule);

      document.getElementById("exportStudentXls")?.addEventListener("click", () => {
        const program = document.getElementById("studentProgram")?.value || "Digital Marketing";
        const weekIdVal = document.getElementById("studentWeekSelect")?.value;
        const weekId = weekIdVal ? Number(weekIdVal) : studentState.activeWeekId;

        const semester = document.getElementById("studentSemester")?.value || "1";
        const qs = new URLSearchParams({ program, year_level: String(activeYear), semester: String(semester) });
        if (weekId) qs.set("week_id", String(weekId));
        window.location.href = `php/export_student_schedule_xls.php?${qs.toString()}`;
      });

      document.getElementById("emailStudentSchedule")?.addEventListener("click", async () => {
        try {
          setStatusById("studentStatus", "Emailing…");
          const program = document.getElementById("studentProgram")?.value || "Digital Marketing";
          const weekIdVal = document.getElementById("studentWeekSelect")?.value;
          const weekId = weekIdVal ? Number(weekIdVal) : studentState.activeWeekId;
          const semester = document.getElementById("studentSemester")?.value || "1";

          const payload = await fetchJson("php/email_student_schedule.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              program,
              year_level: activeYear,
              semester: Number(semester),
              week_id: weekId,
            }),
          });

          if (!payload.success) {
            throw new Error(payload.error || "Failed to email student schedule");
          }
          setStatusById("studentStatus", "Email sent.", "success");
        } catch (err) {
          setStatusById("studentStatus", err.message || "Failed to email schedule", "error");
        }
      });

      document.querySelectorAll(".tabs [data-year]")?.forEach((btn) => {
        btn.addEventListener("click", async () => {
          document.querySelectorAll(".tabs [data-year]").forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          activeYear = Number(btn.dataset.year);
          const next = getGlobalFilters();
          next.year_level = activeYear;
          setGlobalFilters(next);
          await refreshStudentSchedule();
        });
      });

      window.addEventListener("dmportal:globalFiltersChanged", (e) => {
        const d = e.detail || getGlobalFilters();
        if (d.year_level) activeYear = Number(d.year_level);
        if (document.getElementById("studentSemester") && d.semester) {
          document.getElementById("studentSemester").value = String(d.semester);
        }
        document.querySelectorAll(".tabs [data-year]")?.forEach((b) => b.classList.remove("active"));
        document.querySelector(`.tabs [data-year='${activeYear}']`)?.classList.add("active");
        refreshStudentSchedule();
      });

      await refreshStudentSchedule();
    } catch (err) {
      setStatusById("studentStatus", err.message, "error");
    }
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initStudentView = initStudentView;
})();
