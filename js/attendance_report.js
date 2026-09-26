(function () {
  "use strict";

  const { fetchJson, escapeHtml, setStatusById } = window.dmportal || {};

  const state = {
    courses: [],
    data: [],
    allTeachers: new Map(),
  };

  function formatMaybe(value) {
    if (value === null || value === undefined || value === "") return "-";
    const num = Number(value);
    if (!Number.isFinite(num)) return "-";
    return num.toFixed(2);
  }

  function buildCourseOptions(select, courses, selectedId) {
    if (!select) return;
    const options = [
      '<option value="">All courses</option>',
      ...courses.map(
        (course) =>
          `<option value="${escapeHtml(course.course_id)}">${escapeHtml(course.course_name)} (Y${escapeHtml(course.year_level)} S${escapeHtml(course.semester)})</option>`
      ),
    ];
    select.innerHTML = options.join("");
    if (selectedId) select.value = String(selectedId);
  }

  function populateTeacherDropdown(courses) {
    const teacherSelect = document.getElementById("attendanceReportsTeacher");
    if (!teacherSelect) return;

    // Extract unique teachers
    const teachers = new Map();
    courses.forEach(course => {
      if (course.doctor_id && course.doctor_name) {
        teachers.set(course.doctor_id, course.doctor_name);
      }
    });

    state.allTeachers = teachers;

    teacherSelect.innerHTML = '<option value="">All Professors</option>';
    const sortedTeachers = Array.from(teachers.entries()).sort((a, b) => a[1].localeCompare(b[1]));
    
    sortedTeachers.forEach(([id, name]) => {
      const option = document.createElement("option");
      option.value = id;
      option.textContent = name;
      teacherSelect.appendChild(option);
    });
  }

  function renderSummary(metrics) {
    const summaryDiv = document.getElementById("attendanceReportsSummary");
    if (!summaryDiv) return;

    if (!metrics || !Number.isFinite(metrics.courses)) {
      summaryDiv.style.display = 'none';
      return;
    }

    summaryDiv.style.display = 'block';

    document.getElementById("attendanceReportsTotalCourses").textContent = metrics.courses || 0;
    document.getElementById("attendanceReportsTotalSessions").textContent = metrics.total_sessions || 0;
    document.getElementById("attendanceReportsPresent").textContent = metrics.present || 0;
    document.getElementById("attendanceReportsAbsent").textContent = metrics.absent || 0;
    
    const rate = formatMaybe(metrics.attendance_rate);
    document.getElementById("attendanceReportsRate").textContent = rate === "-" ? "-" : `${rate}%`;
  }

  function renderRows(body, courses) {
    if (!body) return;
    body.innerHTML = "";
    if (!Array.isArray(courses) || !courses.length) {
      body.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center; padding:20px;">No attendance data found for the selected filters.</td></tr>';
      return;
    }

    courses.forEach((course) => {
      const tr = document.createElement("tr");
      
      const rate = formatMaybe(course.attendance_rate);
      const rateDisplay = rate === "-" ? "-" : `${rate}%`;
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600; margin-bottom:2px;">${escapeHtml(course.course_name || "")}</div>
          <div class="muted" style="font-size:0.85rem;">${escapeHtml(course.subject_code || "")}</div>
        </td>
        <td>${escapeHtml(course.doctor_name || "-")}</td>
        <td style="text-align:center;">${escapeHtml(course.year_level ?? "-")}</td>
        <td style="text-align:center;">${escapeHtml(course.semester ?? "-")}</td>
        <td style="text-align:center;">${escapeHtml(course.present_records ?? 0)}</td>
        <td style="text-align:center;">${escapeHtml(course.absent_records ?? 0)}</td>
        <td style="text-align:center; font-weight:700;">
          <span style="color: ${parseFloat(rate) >= 75 ? 'var(--success)' : parseFloat(rate) >= 50 ? 'var(--accent)' : 'var(--danger)'}">
            ${rateDisplay}
          </span>
        </td>
      `;
      body.appendChild(tr);
    });
  }

  async function loadSummary() {
    const body = document.getElementById("attendanceReportsBody");
    const courseSelect = document.getElementById("attendanceReportsCourse");

    setStatusById("attendanceReportsStatus", "Loading…");

    const yearLevel = document.getElementById("attendanceReportsYear")?.value || "";
    const semester = document.getElementById("attendanceReportsSemester")?.value || "";

    const qs = new URLSearchParams();
    if (yearLevel) qs.set("year_level", yearLevel);
    if (semester) qs.set("semester", semester);

    try {
      const payload = await fetchJson(`php/get_attendance_reports_summary.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load report");

      state.courses = payload.data?.courses || [];
      state.data = state.courses;

      renderSummary(payload.data?.metrics);
      populateTeacherDropdown(state.courses);
      buildCourseOptions(courseSelect, state.courses);
      renderRows(body, state.courses);

      setStatusById("attendanceReportsStatus", "");
    } catch (err) {
      setStatusById("attendanceReportsStatus", err.message || "Failed to load attendance report", "error");
      renderSummary(null);
      renderRows(body, []);
    }
  }

  function applyFilters() {
    const teacherId = document.getElementById("attendanceReportsTeacher")?.value || "";
    const courseId = document.getElementById("attendanceReportsCourse")?.value || "";
    const body = document.getElementById("attendanceReportsBody");
    
    let filtered = [...state.data];

    // Filter by teacher first
    if (teacherId) {
      filtered = filtered.filter(c => String(c.doctor_id) === teacherId);
    }

    // Then filter by course
    if (courseId) {
      filtered = filtered.filter(c => String(c.course_id) === courseId);
    }

    // Recalculate metrics based on filtered data
    const filteredMetrics = calculateMetrics(filtered);
    renderSummary(filteredMetrics);
    renderRows(body, filtered);

    // Update course dropdown to show only courses for selected teacher
    if (teacherId) {
      const teacherCourses = state.data.filter(c => String(c.doctor_id) === teacherId);
      buildCourseOptions(document.getElementById("attendanceReportsCourse"), teacherCourses, courseId);
    } else {
      buildCourseOptions(document.getElementById("attendanceReportsCourse"), state.data, courseId);
    }
  }

  function calculateMetrics(courses) {
    if (!courses || courses.length === 0) {
      return {
        courses: 0,
        total_sessions: 0,
        present: 0,
        absent: 0,
        attendance_rate: 0,
      };
    }

    let totalSessions = 0;
    let totalPresent = 0;
    let totalAbsent = 0;

    courses.forEach(course => {
      totalSessions += course.total_records || 0;
      totalPresent += course.present_records || 0;
      totalAbsent += course.absent_records || 0;
    });

    const attendanceRate = totalSessions > 0 
      ? parseFloat(((totalPresent / totalSessions) * 100).toFixed(2))
      : 0;

    return {
      courses: courses.length,
      total_sessions: totalSessions,
      present: totalPresent,
      absent: totalAbsent,
      attendance_rate: attendanceRate,
    };
  }

  function filterByCourse() {
    applyFilters();
  }

  function filterByTeacher() {
    // Reset course selection when teacher changes
    const courseSelect = document.getElementById("attendanceReportsCourse");
    if (courseSelect) courseSelect.value = "";
    applyFilters();
  }

  function exportReport() {
    const yearLevel = document.getElementById("attendanceReportsYear")?.value || "";
    const semester = document.getElementById("attendanceReportsSemester")?.value || "";
    const teacherId = document.getElementById("attendanceReportsTeacher")?.value || "";
    const courseId = document.getElementById("attendanceReportsCourse")?.value || "";

    const qs = new URLSearchParams();
    if (yearLevel) qs.set("year_level", yearLevel);
    if (semester) qs.set("semester", semester);
    if (teacherId) qs.set("doctor_id", teacherId);
    if (courseId) qs.set("course_id", courseId);

    window.location.href = `php/export_attendance_report_xls.php?${qs.toString()}`;
  }

  async function initAttendanceReportsPage() {
    await loadSummary();

    document.getElementById("attendanceReportsYear")?.addEventListener("change", loadSummary);
    document.getElementById("attendanceReportsSemester")?.addEventListener("change", loadSummary);
    document.getElementById("attendanceReportsTeacher")?.addEventListener("change", filterByTeacher);
    document.getElementById("attendanceReportsCourse")?.addEventListener("change", filterByCourse);
    document.getElementById("attendanceReportsRefresh")?.addEventListener("click", loadSummary);
    document.getElementById("exportAttendanceReportXls")?.addEventListener("click", exportReport);
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAttendanceReportsPage = initAttendanceReportsPage;
})();
