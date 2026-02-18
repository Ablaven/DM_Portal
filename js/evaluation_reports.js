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
          `<option value="${escapeHtml(course.course_id)}">${escapeHtml(course.course_name)} (Y${escapeHtml(course.year_level)} - S${escapeHtml(course.semester)})</option>`
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
        <div class="report-metric-label">Graded Students</div>
        <div class="report-metric-value">${escapeHtml(metrics.graded_students)}</div>
      </div>
      <div class="report-metric">
        <div class="report-metric-label">Avg Final</div>
        <div class="report-metric-value">${formatMaybe(metrics.avg_final)}</div>
      </div>
      <div class="report-metric">
        <div class="report-metric-label">Avg Attendance</div>
        <div class="report-metric-value">${formatMaybe(metrics.avg_attendance)}</div>
      </div>
    `;
  }

  function renderRows(body, courses) {
    if (!body) return;
    body.innerHTML = "";
    if (!Array.isArray(courses) || !courses.length) {
      body.innerHTML = '<tr><td colspan="7" class="muted">No courses found for the selected filters.</td></tr>';
      return;
    }

    courses.forEach((course) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td data-label="Course">
          <div class="report-course">
            <div class="report-course-title">${escapeHtml(course.course_name || "")}</div>
            <div class="report-course-sub">Y${escapeHtml(course.year_level ?? "-")} · S${escapeHtml(course.semester ?? "-")}</div>
          </div>
        </td>
        <td data-label="Doctors">${escapeHtml(course.doctor_names || "-")}</td>
        <td data-label="Year" class="col-number">${escapeHtml(course.year_level ?? "-")}</td>
        <td data-label="Sem" class="col-number">${escapeHtml(course.semester ?? "-")}</td>
        <td data-label="Avg Final" class="col-number">${formatMaybe(course.avg_final)}</td>
        <td data-label="Avg Attendance" class="col-number">${formatMaybe(course.avg_attendance)}</td>
        <td data-label="Graded" class="col-number">${escapeHtml(course.graded_count ?? 0)}</td>
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
    const status = document.getElementById("evaluationReportsStatus");
    const metricsRoot = document.getElementById("evaluationReportsMetrics");
    const body = document.getElementById("evaluationReportsBody");
    const courseSelect = document.getElementById("evaluationReportsCourse");

    if (status) status.textContent = "Loading…";

    const filters = getEffectivePageFilters?.() || { year_level: 0, semester: 0 };
    const qs = new URLSearchParams();
    if (filters.year_level) qs.set("year_level", String(filters.year_level));
    if (filters.semester) qs.set("semester", String(filters.semester));

    try {
      const payload = await fetchJson(`php/get_evaluation_reports_summary.php?${qs.toString()}`);
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
      if (status) status.textContent = err.message || "Failed to load evaluation report.";
      renderMetrics(metricsRoot, null);
      renderRows(body, []);
    }
  }

  function initEvaluationReportsPage() {
    const root = document.getElementById("evaluationReportsBody");
    if (!root) return;

    initPageFiltersUI?.({ yearSelectId: "evaluationReportsYear", semesterSelectId: "evaluationReportsSemester" });
    window.addEventListener("dmportal:pageFiltersChanged", loadSummary);

    const refreshBtn = document.getElementById("evaluationReportsRefresh");
    refreshBtn?.addEventListener("click", loadSummary);

    const courseSelect = document.getElementById("evaluationReportsCourse");
    courseSelect?.addEventListener("change", loadSummary);

    document.getElementById("exportEvaluationReportSummary")?.addEventListener("click", () => {
      const courseId = Number(courseSelect?.value || 0);
      if (!ensureCourseSelected(courseId, "evaluationReportsStatus")) return;
      window.location.href = `php/export_evaluation_summary_xls.php?course_id=${courseId}`;
    });

    document.getElementById("exportEvaluationReportGrades")?.addEventListener("click", () => {
      const courseId = Number(courseSelect?.value || 0);
      if (!ensureCourseSelected(courseId, "evaluationReportsStatus")) return;
      window.location.href = `php/export_evaluation_grades_xls.php?course_id=${courseId}`;
    });

    document.getElementById("exportEvaluationReportSummaryAll")?.addEventListener("click", () => {
      const filters = getEffectivePageFilters?.() || { year_level: 0, semester: 0 };
      const qs = new URLSearchParams();
      if (filters.year_level) qs.set("year_level", String(filters.year_level));
      if (filters.semester) qs.set("semester", String(filters.semester));
      window.location.href = `php/export_evaluation_summary_all_xls.php?${qs.toString()}`;
    });

    loadSummary();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initEvaluationReportsPage = initEvaluationReportsPage;
})();
