(function () {
  "use strict";

  const { fetchJson, escapeHtml, initPageFiltersUI, getEffectivePageFilters } = window.dmportal || {};

  function formatHours(n) {
    const num = Number(n);
    if (Number.isNaN(num)) return "0.00";
    return num.toFixed(2);
  }

  function renderDoctorCard(doctor) {
    const totals = doctor.totals || {};
    const allocT = Number(totals.allocated_hours || 0);
    const doneT = Number(totals.done_hours || 0);
    const remT = Number(totals.remaining_hours || 0);
    const pct = allocT > 0 ? Math.max(0, Math.min(100, (doneT / allocT) * 100)) : 0;

    const courseRows = (doctor.courses || [])
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

  async function initHoursReportPage() {
    const root = document.getElementById("hoursReportRoot");
    if (!root) return;

    const status = document.getElementById("hoursReportStatus");
    const refreshBtn = document.getElementById("hoursReportRefresh");

    function setStatus(msg, type) {
      if (!status) return;
      status.textContent = msg || "";
      status.classList.remove("success", "error");
      if (type) status.classList.add(type);
    }

    function render(doctors) {
      root.innerHTML = "";
      if (!Array.isArray(doctors) || !doctors.length) {
        root.innerHTML = '<div class="muted">No data yet. Assign doctors to courses first.</div>';
        return;
      }
      root.innerHTML = doctors.map(renderDoctorCard).join("");
    }

    async function load() {
      const f = getEffectivePageFilters();
      const qs = new URLSearchParams();
      if (f.year_level) qs.set("year_level", String(f.year_level));
      if (f.semester) qs.set("semester", String(f.semester));
      setStatus("Loading…");
      try {
        const url = "php/get_hours_report.php" + (qs.toString() ? `?${qs.toString()}` : "");
        const payload = await fetchJson(url);
        if (!payload?.success) throw new Error(payload?.error || "Failed to load hours report.");
        render(payload?.data?.doctors || []);
        setStatus("");
      } catch (err) {
        setStatus(err.message || "Failed to load hours report.", "error");
      }
    }

    initPageFiltersUI({
      yearSelectId: "hoursReportYearFilter",
      semesterSelectId: "hoursReportSemesterFilter",
    });

    window.addEventListener("dmportal:pageFiltersChanged", load);
    refreshBtn?.addEventListener("click", load);

    await load();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initHoursReportPage = initHoursReportPage;
})();
