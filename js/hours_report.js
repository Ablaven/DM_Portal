(function () {
  "use strict";

  const { fetchJson, escapeHtml, initPageFiltersUI, getEffectivePageFilters, formatHours } = window.dmportal || {};

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
              <span class="muted">${formatHours(done)}h / ${formatHours(alloc)}h</span>
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
          <span class="muted">${formatHours(doneT)}h / ${formatHours(allocT)}h</span>
        </div>
        <div class="course-progress-bar" aria-label="Doctor progress">
          <div class="course-progress-fill" style="width:${pct.toFixed(2)}%"></div>
        </div>
        <div class="course-progress-legend">
          <span class="badge">Allocated: ${formatHours(allocT)}h</span>
          <span class="badge badge-success">Done: ${formatHours(doneT)}h</span>
          <span class="badge badge-danger">Remaining: ${formatHours(remT)}h</span>
          <span class="muted">${pct.toFixed(0)}%</span>
        </div>
        <div class="hours-report-courses">${courseRows}</div>
      </div>
    `;
  }

  function buildFilterQueryString() {
    const f = getEffectivePageFilters();
    const qs = new URLSearchParams();
    if (f.year_level) qs.set("year_level", String(f.year_level));
    if (f.semester) qs.set("semester", String(f.semester));
    return qs;
  }

  function setModalOpen(modal, open) {
    if (!modal) return;
    modal.setAttribute("aria-hidden", open ? "false" : "true");
    modal.classList.toggle("open", open);
  }

  function setStatusById(id, msg, type) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg || "";
    el.classList.remove("success", "error");
    if (type) el.classList.add(type);
  }

  function buildCustomExportPayload({ isTeacher, teacherDoctorId, doctorIds }) {
    const filters = [];
    document.querySelectorAll("#hoursReportCustomYearSem input[type='checkbox']:checked").forEach((cb) => {
      const v = String(cb.value || "");
      const [y, s] = v.split("-").map((x) => Number(x));
      if ([1, 2, 3].includes(y) && [1, 2].includes(s)) {
        filters.push({ year_level: y, semester: s });
      }
    });

    const normalizedDoctorIds = isTeacher ? [teacherDoctorId] : (doctorIds || []).map((x) => Number(x)).filter((x) => Number.isFinite(x) && x > 0);

    // Sort and dedupe
    const uniqDoctors = [...new Set(normalizedDoctorIds)].sort((a, b) => a - b);
    const uniqFilters = filters
      .map((f) => `${f.year_level}-${f.semester}`)
      .filter((v, i, arr) => arr.indexOf(v) === i)
      .map((k) => {
        const [y, s] = k.split("-").map((x) => Number(x));
        return { year_level: y, semester: s };
      })
      .sort((a, b) => (a.year_level - b.year_level) || (a.semester - b.semester));

    return { doctor_ids: uniqDoctors, filters: uniqFilters };
  }

  async function initHoursReportPage(options = {}) {
    const root = document.getElementById("hoursReportRoot");
    if (!root) return;

    const isTeacher = Boolean(options.isTeacher);
    const teacherDoctorId = Number(options.teacherDoctorId || 0);

    const status = document.getElementById("hoursReportStatus");
    const refreshBtn = document.getElementById("hoursReportRefresh");
    const doctorSelect = document.getElementById("hoursReportDoctorFilter");
    const customBtn = document.getElementById("exportHoursReportCustomXls");
    const customModal = document.getElementById("hoursReportCustomExportModal");
    const customDoctorsRoot = document.getElementById("hoursReportCustomDoctors");
    const customRunBtn = document.getElementById("hoursReportCustomExportRun");
    const doctorSearch = document.getElementById("hoursReportCustomDoctorSearch");
    const doctorsSelectAllBtn = document.getElementById("hoursReportCustomDoctorsSelectAll");
    const doctorsClearBtn = document.getElementById("hoursReportCustomDoctorsClear");
    const yearSemSelectAllBtn = document.getElementById("hoursReportCustomYearSemSelectAll");
    const yearSemClearBtn = document.getElementById("hoursReportCustomYearSemClear");

    let customSelectedDoctorIds = [];

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

    async function loadDoctorsDropdown() {
      if (!doctorSelect || isTeacher) return;
      try {
        const payload = await fetchJson("php/get_doctors.php");
        if (!payload?.success) return;
        const doctors = Array.isArray(payload?.data) ? payload.data : (payload?.data?.doctors || []);
        const current = doctorSelect.value;
        doctorSelect.innerHTML = '<option value="">Select professor…</option>';
        doctors.forEach((d) => {
          const opt = document.createElement("option");
          opt.value = String(d.doctor_id);
          opt.textContent = d.full_name || `Doctor #${d.doctor_id}`;
          doctorSelect.appendChild(opt);
        });
        if (current && [...doctorSelect.options].some((o) => o.value === current)) {
          doctorSelect.value = current;
        }
      } catch {
        // Non-fatal; detail export still works if user knows doctor id from UI cards
      }
    }

    async function loadDoctorsForCustomModal() {
      if (isTeacher || !customDoctorsRoot) return;
      try {
        const payload = await fetchJson("php/get_doctors.php");
        if (!payload?.success) return;
        const doctors = Array.isArray(payload?.data) ? payload.data : (payload?.data?.doctors || []);

        customDoctorsRoot.innerHTML = "";
        const items = [];
        doctors.forEach((d) => {
          const id = Number(d.doctor_id || 0);
          if (!id) return;
          const label = d.full_name || `Doctor #${id}`;

          const wrap = document.createElement("label");
          wrap.className = "chk";
          wrap.dataset.doctorId = String(id);
          wrap.dataset.doctorName = String(label).toLowerCase();
          const cb = document.createElement("input");
          cb.type = "checkbox";
          cb.value = String(id);
          cb.addEventListener("change", () => {
            const next = new Set(customSelectedDoctorIds);
            if (cb.checked) next.add(id);
            else next.delete(id);
            customSelectedDoctorIds = [...next];
          });
          wrap.appendChild(cb);
          wrap.appendChild(document.createTextNode(" " + label));
          items.push(wrap);
        });
        items.forEach((el) => customDoctorsRoot.appendChild(el));
      } catch {
        // ignore
      }
    }

    function filterDoctorsList() {
      if (!doctorSearch || !customDoctorsRoot) return;
      const q = String(doctorSearch.value || "").trim().toLowerCase();
      customDoctorsRoot.querySelectorAll("label.chk").forEach((el) => {
        const name = String(el.dataset.doctorName || "");
        el.style.display = q === "" || name.includes(q) ? "" : "none";
      });
    }

    function setAllCheckboxes(containerId, checked) {
      document.querySelectorAll(`#${containerId} input[type='checkbox']`).forEach((cb) => {
        cb.checked = Boolean(checked);
        cb.dispatchEvent(new Event("change", { bubbles: true }));
      });
    }

    function submitCustomExport(payload) {
      // Use a real form POST so the browser handles file download.
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "php/export_hours_report_custom_xls.php";
      form.style.display = "none";

      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "payload";
      input.value = JSON.stringify(payload);
      form.appendChild(input);

      document.body.appendChild(form);
      form.submit();
      form.remove();
    }

    async function load() {
      const qs = buildFilterQueryString();
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

    document.getElementById("exportHoursReportSummaryXls")?.addEventListener("click", () => {
      const qs = buildFilterQueryString();
      window.location.href = `php/export_hours_report_summary_xls.php?${qs.toString()}`;
    });

    document.getElementById("exportHoursReportDetailXls")?.addEventListener("click", () => {
      const doctorId = isTeacher ? teacherDoctorId : Number(doctorSelect?.value || 0);
      if (!doctorId) {
        setStatus("Select a professor first.", "error");
        return;
      }
      const qs = buildFilterQueryString();
      qs.set("doctor_id", String(doctorId));
      window.location.href = `php/export_hours_report_detail_xls.php?${qs.toString()}`;
    });

    // Custom export modal wiring
    customBtn?.addEventListener("click", async () => {
      setStatusById("hoursReportCustomExportStatus", "");
      if (!isTeacher) {
        customSelectedDoctorIds = [];
        await loadDoctorsForCustomModal();
        if (doctorSearch) doctorSearch.value = "";
        filterDoctorsList();
      }
      setModalOpen(customModal, true);
    });

    customModal?.querySelectorAll("[data-close='1']")?.forEach((el) => {
      el.addEventListener("click", () => setModalOpen(customModal, false));
    });

    doctorSearch?.addEventListener("input", filterDoctorsList);
    doctorsSelectAllBtn?.addEventListener("click", () => setAllCheckboxes("hoursReportCustomDoctors", true));
    doctorsClearBtn?.addEventListener("click", () => setAllCheckboxes("hoursReportCustomDoctors", false));
    yearSemSelectAllBtn?.addEventListener("click", () => setAllCheckboxes("hoursReportCustomYearSem", true));
    yearSemClearBtn?.addEventListener("click", () => setAllCheckboxes("hoursReportCustomYearSem", false));

    customRunBtn?.addEventListener("click", () => {
      setStatusById("hoursReportCustomExportStatus", "");
      if (isTeacher && teacherDoctorId <= 0) {
        setStatusById("hoursReportCustomExportStatus", "Your account is missing doctor_id.", "error");
        return;
      }

      const payload = buildCustomExportPayload({
        isTeacher,
        teacherDoctorId,
        doctorIds: customSelectedDoctorIds,
      });

      if (!payload.filters.length) {
        setStatusById("hoursReportCustomExportStatus", "Select at least one Year/Semester.", "error");
        return;
      }
      if (!payload.doctor_ids.length) {
        setStatusById("hoursReportCustomExportStatus", "Select at least one doctor.", "error");
        return;
      }

      setStatusById("hoursReportCustomExportStatus", "Preparing export…");
      submitCustomExport(payload);
      setModalOpen(customModal, false);
    });

    await loadDoctorsDropdown();
    await load();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initHoursReportPage = initHoursReportPage;
})();
