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
    const teacherSelect = document.getElementById("evaluationReportsTeacher");
    if (!teacherSelect) return;

    // Extract unique teachers from doctor_names
    const teachers = new Map();
    courses.forEach(course => {
      if (course.doctor_names) {
        // doctor_names might be comma-separated, split and add each
        const names = course.doctor_names.split(',').map(n => n.trim());
        names.forEach(name => {
          if (name && !teachers.has(name)) {
            teachers.set(name, name);
          }
        });
      }
    });

    state.allTeachers = teachers;

    teacherSelect.innerHTML = '<option value="">All Professors</option>';
    const sortedTeachers = Array.from(teachers.keys()).sort();
    
    sortedTeachers.forEach(name => {
      const option = document.createElement("option");
      option.value = name;
      option.textContent = name;
      teacherSelect.appendChild(option);
    });
  }

  function renderSummary(metrics) {
    const summaryDiv = document.getElementById("evaluationReportsSummary");
    if (!summaryDiv) return;

    if (!metrics || !Number.isFinite(metrics.courses)) {
      summaryDiv.style.display = 'none';
      return;
    }

    summaryDiv.style.display = 'block';

    document.getElementById("evaluationReportsTotalCourses").textContent = metrics.courses || 0;
    document.getElementById("evaluationReportsGradedStudents").textContent = metrics.graded_students || 0;
    
    const avgFinal = formatMaybe(metrics.avg_final);
    document.getElementById("evaluationReportsAvgFinal").textContent = avgFinal;
    
    const avgAttendance = formatMaybe(metrics.avg_attendance);
    document.getElementById("evaluationReportsAvgAttendance").textContent = avgAttendance === "-" ? "-" : `${avgAttendance}%`;
  }

  function renderRows(body, courses) {
    if (!body) return;
    body.innerHTML = "";
    if (!Array.isArray(courses) || !courses.length) {
      body.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center; padding:20px;">No evaluation data found for the selected filters.</td></tr>';
      return;
    }

    courses.forEach((course) => {
      const tr = document.createElement("tr");
      
      const avgFinal = formatMaybe(course.avg_final);
      const avgAttendance = formatMaybe(course.avg_attendance);
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600; margin-bottom:2px;">${escapeHtml(course.course_name || "")}</div>
          <div class="muted" style="font-size:0.85rem;">${escapeHtml(course.subject_code || "")}</div>
        </td>
        <td>${escapeHtml(course.doctor_names || "-")}</td>
        <td style="text-align:center;">${escapeHtml(course.year_level ?? "-")}</td>
        <td style="text-align:center;">${escapeHtml(course.semester ?? "-")}</td>
        <td style="text-align:center; font-weight:700;">
          <span style="color: ${parseFloat(avgFinal) >= 70 ? 'var(--success)' : parseFloat(avgFinal) >= 50 ? 'var(--accent)' : 'var(--danger)'}">
            ${avgFinal}
          </span>
        </td>
        <td style="text-align:center;">${avgAttendance === "-" ? "-" : `${avgAttendance}%`}</td>
        <td style="text-align:center;">${escapeHtml(course.graded_count ?? 0)}</td>
      `;
      body.appendChild(tr);
    });
  }

  async function loadSummary() {
    const body = document.getElementById("evaluationReportsBody");
    const courseSelect = document.getElementById("evaluationReportsCourse");

    setStatusById("evaluationReportsStatus", "Loading…");

    const yearLevel = document.getElementById("evaluationReportsYear")?.value || "";
    const semester = document.getElementById("evaluationReportsSemester")?.value || "";

    const qs = new URLSearchParams();
    if (yearLevel) qs.set("year_level", yearLevel);
    if (semester) qs.set("semester", semester);

    try {
      const payload = await fetchJson(`php/get_evaluation_reports_summary.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load report");

      state.courses = payload.data?.courses || [];
      state.data = state.courses;

      renderSummary(payload.data?.metrics);
      populateTeacherDropdown(state.courses);
      buildCourseOptions(courseSelect, state.courses);
      renderRows(body, state.courses);

      setStatusById("evaluationReportsStatus", "");
    } catch (err) {
      setStatusById("evaluationReportsStatus", err.message || "Failed to load evaluation report", "error");
      renderSummary(null);
      renderRows(body, []);
    }
  }

  function calculateMetrics(courses) {
    if (!courses || courses.length === 0) {
      return {
        courses: 0,
        graded_students: 0,
        avg_final: 0,
        avg_attendance: 0,
      };
    }

    let totalGraded = 0;
    let totalFinalSum = 0;
    let totalAttendanceSum = 0;
    let finalCount = 0;
    let attendanceCount = 0;

    courses.forEach(course => {
      totalGraded += course.graded_count || 0;
      
      const avgFinal = parseFloat(course.avg_final);
      if (Number.isFinite(avgFinal)) {
        totalFinalSum += avgFinal;
        finalCount++;
      }
      
      const avgAtt = parseFloat(course.avg_attendance);
      if (Number.isFinite(avgAtt)) {
        totalAttendanceSum += avgAtt;
        attendanceCount++;
      }
    });

    return {
      courses: courses.length,
      graded_students: totalGraded,
      avg_final: finalCount > 0 ? parseFloat((totalFinalSum / finalCount).toFixed(2)) : 0,
      avg_attendance: attendanceCount > 0 ? parseFloat((totalAttendanceSum / attendanceCount).toFixed(2)) : 0,
    };
  }

  function applyFilters() {
    const teacherName = document.getElementById("evaluationReportsTeacher")?.value || "";
    const courseId = document.getElementById("evaluationReportsCourse")?.value || "";
    const body = document.getElementById("evaluationReportsBody");
    
    let filtered = [...state.data];

    // Filter by teacher name (search in doctor_names field)
    if (teacherName) {
      filtered = filtered.filter(c => {
        const names = (c.doctor_names || "").toLowerCase();
        return names.includes(teacherName.toLowerCase());
      });
    }

    // Then filter by course
    if (courseId) {
      filtered = filtered.filter(c => String(c.course_id) === courseId);
    }

    // Recalculate metrics
    const filteredMetrics = calculateMetrics(filtered);
    renderSummary(filteredMetrics);
    renderRows(body, filtered);

    // Update course dropdown to show only courses for selected teacher
    if (teacherName) {
      const teacherCourses = state.data.filter(c => {
        const names = (c.doctor_names || "").toLowerCase();
        return names.includes(teacherName.toLowerCase());
      });
      buildCourseOptions(document.getElementById("evaluationReportsCourse"), teacherCourses, courseId);
    } else {
      buildCourseOptions(document.getElementById("evaluationReportsCourse"), state.data, courseId);
    }
  }

  function filterByCourse() {
    applyFilters();
  }

  function filterByTeacher() {
    const courseSelect = document.getElementById("evaluationReportsCourse");
    if (courseSelect) courseSelect.value = "";
    applyFilters();
  }

  function ensureCourseSelected(courseId, statusId) {
    if (!courseId) {
      setStatusById?.(statusId, "Select a course first.", "error");
      return false;
    }
    return true;
  }

  async function initEvaluationReportsPage() {
    await loadSummary();

    document.getElementById("evaluationReportsYear")?.addEventListener("change", loadSummary);
    document.getElementById("evaluationReportsSemester")?.addEventListener("change", loadSummary);
    document.getElementById("evaluationReportsTeacher")?.addEventListener("change", filterByTeacher);
    document.getElementById("evaluationReportsCourse")?.addEventListener("change", filterByCourse);
    document.getElementById("evaluationReportsRefresh")?.addEventListener("click", loadSummary);

    // Export dropdown toggle
    const exportBtn = document.getElementById("evaluationReportsExportBtn");
    const exportMenu = document.getElementById("evaluationReportsExportMenu");
    
    if (exportBtn && exportMenu) {
      exportBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        const isVisible = exportMenu.style.display === "block";
        exportMenu.style.display = isVisible ? "none" : "block";
      });

      // Close dropdown when clicking outside
      document.addEventListener("click", (e) => {
        if (exportMenu.style.display === "block" && !exportMenu.contains(e.target) && e.target !== exportBtn) {
          exportMenu.style.display = "none";
        }
      });

      // Hover effects for dropdown items
      const menuItems = exportMenu.querySelectorAll("button");
      menuItems.forEach(item => {
        item.addEventListener("mouseenter", function() {
          this.style.background = "var(--surface-1)";
        });
        item.addEventListener("mouseleave", function() {
          this.style.background = "transparent";
        });
      });
    }

    // Export buttons
    const courseSelect = document.getElementById("evaluationReportsCourse");

    document.getElementById("exportEvaluationReportSummary")?.addEventListener("click", () => {
      const courseId = Number(courseSelect?.value || 0);
      if (!ensureCourseSelected(courseId, "evaluationReportsStatus")) return;
      if (exportMenu) exportMenu.style.display = "none";
      window.location.href = `php/export_evaluation_summary_xls.php?course_id=${courseId}`;
    });

    document.getElementById("exportEvaluationReportGrades")?.addEventListener("click", () => {
      const courseId = Number(courseSelect?.value || 0);
      if (!ensureCourseSelected(courseId, "evaluationReportsStatus")) return;
      if (exportMenu) exportMenu.style.display = "none";
      window.location.href = `php/export_evaluation_grades_xls.php?course_id=${courseId}`;
    });

    document.getElementById("exportEvaluationReportSummaryAll")?.addEventListener("click", () => {
      const yearLevel = document.getElementById("evaluationReportsYear")?.value || "";
      const semester = document.getElementById("evaluationReportsSemester")?.value || "";
      const qs = new URLSearchParams();
      if (yearLevel) qs.set("year_level", yearLevel);
      if (semester) qs.set("semester", semester);
      if (exportMenu) exportMenu.style.display = "none";
      window.location.href = `php/export_evaluation_summary_all_xls.php?${qs.toString()}`;
    });
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initEvaluationReportsPage = initEvaluationReportsPage;
})();
