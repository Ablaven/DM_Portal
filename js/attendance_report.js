(function () {
  "use strict";

  const { fetchJson, escapeHtml, initPageFiltersUI, getEffectivePageFilters, setStatusById } = window.dmportal || {};

  function formatMaybe(value) {
    if (value === null || value === undefined || value === "") return "—";
    const num = Number(value);
    if (!Number.isFinite(num)) return "—";
    return num.toFixed(2);
  }

  function buildCourseOptions(select, courses, selectedId) {
    if (!select) return;
    const options = [
      '<option value="">All courses</option>',
      ...courses.map(
        (course) =>
          `<option value="${escapeHtml(course.course_id)}">${escapeHtml(course.course_name)} (Y${escapeHtml(course.year_level)})</option>`
      ),
    ];
    select.innerHTML = options.join("");
    if (selectedId) select.value = String(selectedId);
  }

  function renderMetrics(root, metrics) {
    if (!root) return;
    if (!metrics || !Number.isFinite(metrics.courses)) {
      root.innerHTML = '<div class="muted">No data available.</div>';
      return;
    }

    root.innerHTML = `
      <div class="report-metric">
        <div class="report-metric-label">Courses</div>
        <div class="report-metric-value">${escapeHtml(metrics.courses)}</div>
      </div>
      <div class="report-metric">
        <div class="report-metric-label">Total Sessions</div>
        <div class="report-metric-value">${escapeHtml(metrics.total_sessions)}</div>
      </div>
      <div class="report-metric">
        <div class="report-metric-label">Present</div>
        <div class="report-metric-value">${escapeHtml(metrics.present)}</div>
      </div>
      <div class="report-metric">
        <div class="report-metric-label">Attendance Rate</div>
        <div class="report-metric-value">${formatMaybe(metrics.attendance_rate)}%</div>
      </div>
    `;
  }

  function renderRows(body, courses) {
    if (!body) return;
    body.innerHTML = "";
    if (!Array.isArray(courses) || !courses.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted">No attendance data found for the selected filters.</td></tr>';
      return;
    }

    courses.forEach((course) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td data-label="Course">
          <div class="report-course">
            <div class="report-course-title">${escapeHtml(course.course_name || "")}</div>
            <div class="report-course-sub">Y${escapeHtml(course.year_level ?? "-")}</div>
          </div>
        </td>
        <td data-label="Doctor">${escapeHtml(course.doctor_name || "-")}</td>
        <td data-label="Year" class="col-number">${escapeHtml(course.year_level ?? "-")}</td>
        <td data-label="Present" class="col-number">${escapeHtml(course.present_records ?? 0)}</td>
        <td data-label="Absent" class="col-number">${escapeHtml(course.absent_records ?? 0)}</td>
        <td data-label="Attendance Rate" class="col-number">${formatMaybe(course.attendance_rate)}%</td>
      `;
      body.appendChild(tr);
    });
  }

  function ensureCourseSelected(courseId, statusId) {
    if (!courseId) {
      setStatusById?.(statusId, "Select a course first.", "error");
      return false;
    }
    return true;
  }

  async function loadSummary() {
    const status = document.getElementById("attendanceReportsStatus");
    const metricsRoot = document.getElementById("attendanceReportsMetrics");
    const body = document.getElementById("attendanceReportsBody");
    const courseSelect = document.getElementById("attendanceReportsCourse");

    if (status) status.textContent = "Loading…";

    const filters = getEffectivePageFilters?.() || { year_level: 0 };
    const qs = new URLSearchParams();
    if (filters.year_level) qs.set("year_level", String(filters.year_level));

    try {
      const payload = await fetchJson(`php/get_attendance_reports_summary.php?${qs.toString()}`);
      const data = payload?.data || {};
      const courses = data.courses || [];
      renderMetrics(metricsRoot, data.metrics || {});
      buildCourseOptions(courseSelect, courses, courseSelect?.value);

      const selectedCourse = courseSelect?.value ? Number(courseSelect.value) : 0;
      const filteredCourses = selectedCourse
        ? courses.filter((course) => Number(course.course_id) === selectedCourse)
        : courses;

      renderRows(body, filteredCourses);
      if (status) status.textContent = "";
    } catch (err) {
      if (status) status.textContent = err.message || "Failed to load attendance report.";
      renderMetrics(metricsRoot, null);
      renderRows(body, []);
    }
  }

  function initAttendanceReportsPage() {
    const root = document.getElementById("attendanceReportsBody");
    if (!root) return;

    initPageFiltersUI?.({ yearSelectId: "attendanceReportsYear" });
    window.addEventListener("dmportal:pageFiltersChanged", loadSummary);

    const refreshBtn = document.getElementById("attendanceReportsRefresh");
    refreshBtn?.addEventListener("click", loadSummary);

    const courseSelect = document.getElementById("attendanceReportsCourse");
    courseSelect?.addEventListener("change", loadSummary);

    document.getElementById("exportAttendanceReportXls")?.addEventListener("click", () => {
      const courseId = Number(courseSelect?.value || 0);
      if (!ensureCourseSelected(courseId, "attendanceReportsStatus")) return;
      const weekId = 0;
      window.location.href = `php/export_attendance_xls.php?course_id=${courseId}&end_week_id=${weekId}`;
    });

    loadSummary();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAttendanceReportsPage = initAttendanceReportsPage;
})();
