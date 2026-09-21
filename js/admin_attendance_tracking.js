(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};

  const state = {
    weeks: [],
    terms: [],
    reportData: [],
    filteredData: [],
    allCourses: new Map(),
    allProfessors: new Map(),
  };

  async function loadTerms() {
    try {
      const payload = await fetchJson("php/get_terms.php");
      if (!payload.success) throw new Error(payload.error || "Failed to load terms");
      state.terms = payload.data || [];
      populateTermSelector();
    } catch (err) {
      console.error("Failed to load terms:", err);
    }
  }

  function populateTermSelector() {
    const termSelect = document.getElementById("attendanceTrackingTermFilter");
    if (!termSelect) return;

    termSelect.innerHTML = '<option value="">All Terms</option>';

    for (const term of state.terms) {
      const option = document.createElement("option");
      option.value = term.term_id;
      
      // Build label with academic year info
      let label = term.label || `Semester ${term.semester}`;
      
      // Add academic year if available
      if (term.academic_year_label) {
        label += ` - ${term.academic_year_label}`;
      } else if (term.academic_year_id) {
        label += ` (Year ${term.academic_year_id})`;
      }
      
      if (term.status === 'active') {
        label += ' (Active)';
        option.selected = true; // Auto-select active term
      }
      
      option.textContent = label;
      termSelect.appendChild(option);
    }

    // Trigger week filter update
    filterWeeksByTerm();
  }

  function filterWeeksByTerm() {
    const selectedTermId = document.getElementById("attendanceTrackingTermFilter")?.value || "";
    const fromSelect = document.getElementById("attendanceTrackingFromWeek");
    const toSelect = document.getElementById("attendanceTrackingToWeek");

    if (!fromSelect || !toSelect) return;

    // Filter weeks by selected term
    const filteredWeeks = selectedTermId 
      ? state.weeks.filter(w => String(w.term_id) === selectedTermId)
      : state.weeks;

    // Repopulate week selectors
    fromSelect.innerHTML = '<option value="">Select week…</option>';
    toSelect.innerHTML = '<option value="">Select week…</option>';

    for (const week of filteredWeeks) {
      const option = document.createElement("option");
      option.value = week.week_id;
      option.textContent = week.label || `Week ${week.week_id}`;

      fromSelect.appendChild(option);
      toSelect.appendChild(option.cloneNode(true));
    }
  }

  async function loadWeeks() {
    try {
      // Load weeks for all terms by fetching each term's weeks
      state.weeks = [];
      
      for (const term of state.terms) {
        const payload = await fetchJson(`php/get_weeks.php?term_id=${term.term_id}`);
        if (payload.success && payload.data) {
          // Add term_id to each week
          const weeksWithTerm = payload.data.map(w => ({ ...w, term_id: term.term_id }));
          state.weeks.push(...weeksWithTerm);
        }
      }
      
      // Sort weeks by week_id
      state.weeks.sort((a, b) => a.week_id - b.week_id);
      
      filterWeeksByTerm();
    } catch (err) {
      console.error("Failed to load weeks:", err);
    }
  }

  async function loadReport() {
    const fromWeekId = parseInt(document.getElementById("attendanceTrackingFromWeek")?.value || "0", 10);
    const toWeekId = parseInt(document.getElementById("attendanceTrackingToWeek")?.value || "0", 10);

    if (fromWeekId <= 0 || toWeekId <= 0) {
      setStatusById("attendanceTrackingStatus", "Please select both From and To weeks.", "error");
      return;
    }

    if (fromWeekId > toWeekId) {
      setStatusById("attendanceTrackingStatus", "From week must be before or equal to To week.", "error");
      return;
    }

    try {
      setStatusById("attendanceTrackingStatus", "Loading report…");
      
      const payload = await fetchJson(
        `php/get_attendance_tracking_report.php?week_id_from=${fromWeekId}&week_id_to=${toWeekId}`
      );

      if (!payload.success) throw new Error(payload.error || "Failed to load report");

      state.reportData = payload.data || [];
      state.filteredData = [...state.reportData];

      populateFilterDropdowns();
      renderSummary();
      renderTable();
      
      document.getElementById("attendanceTrackingSummary").style.display = "block";
      document.getElementById("attendanceTrackingTableWrap").style.display = "block";
      document.getElementById("exportAttendanceTrackingReport").disabled = false;

      setStatusById("attendanceTrackingStatus", "");
    } catch (err) {
      setStatusById("attendanceTrackingStatus", err.message || "Failed to load report", "error");
      state.reportData = [];
      state.filteredData = [];
      document.getElementById("attendanceTrackingSummary").style.display = "none";
      document.getElementById("attendanceTrackingTableWrap").style.display = "none";
      document.getElementById("exportAttendanceTrackingReport").disabled = true;
    }
  }

  function populateFilterDropdowns() {
    // Get unique courses and professors
    const courses = new Map();
    const professors = new Map();

    for (const item of state.reportData) {
      if (item.course_id && item.course_name) {
        courses.set(item.course_id, {
          name: item.course_name,
          doctor_id: item.doctor_id,
        });
      }
      if (item.doctor_id && item.doctor_name) {
        professors.set(item.doctor_id, item.doctor_name);
      }
    }

    // Store for filtering
    state.allCourses = courses;
    state.allProfessors = professors;

    // Populate professor dropdown
    const profSelect = document.getElementById("attendanceTrackingFilterProfessor");
    if (profSelect) {
      profSelect.innerHTML = '<option value="">All Professors</option>';
      const sortedProfs = Array.from(professors.entries()).sort((a, b) => a[1].localeCompare(b[1]));
      for (const [id, name] of sortedProfs) {
        const option = document.createElement("option");
        option.value = id;
        option.textContent = name;
        profSelect.appendChild(option);
      }
    }

    // Initial course dropdown population
    updateCourseDropdown();
  }

  function updateCourseDropdown() {
    const profFilter = document.getElementById("attendanceTrackingFilterProfessor")?.value || "";
    const courseSelect = document.getElementById("attendanceTrackingFilterCourse");
    
    if (!courseSelect) return;

    // Get courses filtered by selected professor
    const availableCourses = new Map();
    
    for (const [courseId, courseData] of state.allCourses.entries()) {
      // If professor is selected, only show their courses
      if (profFilter && String(courseData.doctor_id) !== profFilter) {
        continue;
      }
      availableCourses.set(courseId, courseData.name);
    }

    // Save current selection
    const currentSelection = courseSelect.value;

    // Rebuild dropdown
    courseSelect.innerHTML = '<option value="">All Courses</option>';
    const sortedCourses = Array.from(availableCourses.entries()).sort((a, b) => a[1].localeCompare(b[1]));
    for (const [id, name] of sortedCourses) {
      const option = document.createElement("option");
      option.value = id;
      option.textContent = name;
      courseSelect.appendChild(option);
    }

    // Restore selection if still valid
    if (currentSelection && availableCourses.has(parseInt(currentSelection))) {
      courseSelect.value = currentSelection;
    }
  }

  function renderSummary() {
    let total = 0;
    let taken = 0;
    let missing = 0;
    let canceled = 0;

    for (const row of state.reportData) {
      total++;
      if (row.is_canceled) {
        canceled++;
      } else if (row.attendance_taken && row.hours_counted) {
        taken++;
      } else {
        missing++;
      }
    }

    document.getElementById("attendanceTrackingTotal").textContent = total;
    document.getElementById("attendanceTrackingTaken").textContent = taken;
    document.getElementById("attendanceTrackingMissing").textContent = missing;
    document.getElementById("attendanceTrackingCanceled").textContent = canceled;
  }

  function renderTable() {
    const tbody = document.querySelector("#attendanceTrackingTable tbody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!state.filteredData.length) {
      const row = tbody.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 8;
      cell.className = "muted";
      cell.style.textAlign = "center";
      cell.style.padding = "20px";
      cell.textContent = "No data to display";
      return;
    }

    for (const item of state.filteredData) {
      const row = tbody.insertRow();

      // Apply row styling
      if (item.is_canceled) {
        row.style.background = "rgba(156, 163, 175, 0.1)";
      } else if (!item.attendance_taken) {
        row.style.background = "rgba(239, 68, 68, 0.1)";
      } else if (item.hours_counted) {
        row.style.background = "rgba(16, 185, 129, 0.1)";
      }

      // Week
      row.insertCell().textContent = item.week_label || `Week ${item.week_id}`;

      // Date
      const dateCell = row.insertCell();
      if (item.lecture_date) {
        const d = new Date(item.lecture_date + 'T00:00:00');
        dateCell.textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      } else {
        dateCell.textContent = "—";
      }

      // Day
      row.insertCell().textContent = item.day_of_week || "—";

      // Time
      const timeCell = row.insertCell();
      if (item.lecture_time) {
        const [start, end] = item.lecture_time.split(' - ');
        timeCell.innerHTML = `<div style="white-space:nowrap;">${start}</div><div style="white-space:nowrap;font-size:0.85rem;color:var(--muted);">${end}</div>`;
      } else {
        timeCell.textContent = "—";
      }

      // Course
      const courseCell = row.insertCell();
      const courseName = escapeHtml(item.course_name || "");
      const subjectCode = item.subject_code ? escapeHtml(item.subject_code) : "";
      const courseType = item.course_type ? escapeHtml(item.course_type) : "";
      courseCell.innerHTML = `
        <div style="font-weight:600;margin-bottom:4px;">${courseName}</div>
        <div class="muted" style="font-size:0.85rem;">${subjectCode}${courseType ? ` • ${courseType}` : ''}</div>
        <div class="muted" style="font-size:0.8rem;margin-top:2px;">${escapeHtml(item.program || "")} • Y${item.year_level} S${item.semester}</div>
      `;

      // Professor
      const doctorCell = row.insertCell();
      doctorCell.innerHTML = `
        <div style="font-weight:500;">${escapeHtml(item.doctor_name || "")}</div>
        ${item.doctor_email ? `<div class="muted" style="font-size:0.85rem;margin-top:2px;">${escapeHtml(item.doctor_email)}</div>` : ""}
      `;

      // Status
      const statusCell = row.insertCell();
      if (item.is_canceled) {
        statusCell.innerHTML = '<span class="badge" style="background:#9CA3AF;color:#fff;">Canceled</span>';
      } else {
        statusCell.innerHTML = '<span class="badge badge-success">Active</span>';
      }

      // Attendance
      const attendanceCell = row.insertCell();
      if (item.is_canceled) {
        attendanceCell.innerHTML = '<span class="muted">N/A</span>';
      } else if (item.attendance_taken && item.hours_counted) {
        attendanceCell.innerHTML = '<span class="badge badge-success" style="font-size:0.9rem;">✓ Yes</span>';
      } else if (item.attendance_taken && !item.hours_counted) {
        attendanceCell.innerHTML = '<span class="badge" style="background:#F59E0B;color:#fff;font-size:0.9rem;">Opened</span>';
      } else {
        attendanceCell.innerHTML = '<span class="badge badge-danger" style="font-size:0.9rem;">✗ No</span>';
      }
    }
  }

  function applyFilters() {
    const courseFilter = document.getElementById("attendanceTrackingFilterCourse")?.value || "";
    const profFilter = document.getElementById("attendanceTrackingFilterProfessor")?.value || "";
    const statusFilter = document.getElementById("attendanceTrackingFilterStatus")?.value || "";

    state.filteredData = state.reportData.filter((item) => {
      // Course filter
      if (courseFilter && String(item.course_id) !== courseFilter) {
        return false;
      }

      // Professor filter
      if (profFilter && String(item.doctor_id) !== profFilter) {
        return false;
      }

      // Status filter
      if (statusFilter === "missing" && (item.is_canceled || item.attendance_taken)) {
        return false;
      }
      if (statusFilter === "taken" && (!item.attendance_taken || !item.hours_counted)) {
        return false;
      }
      if (statusFilter === "canceled" && !item.is_canceled) {
        return false;
      }

      return true;
    });

    renderTable();
  }

  function exportReport() {
    const fromWeekId = parseInt(document.getElementById("attendanceTrackingFromWeek")?.value || "0", 10);
    const toWeekId = parseInt(document.getElementById("attendanceTrackingToWeek")?.value || "0", 10);

    if (fromWeekId <= 0 || toWeekId <= 0) {
      alert("Please select both From and To weeks first.");
      return;
    }

    const url = `php/export_attendance_tracking.php?week_id_from=${fromWeekId}&week_id_to=${toWeekId}`;
    window.location.href = url;
  }

  async function initAttendanceTracking() {
    await loadTerms();
    await loadWeeks();

    const termFilter = document.getElementById("attendanceTrackingTermFilter");
    if (termFilter) {
      termFilter.addEventListener("change", filterWeeksByTerm);
    }

    const loadBtn = document.getElementById("loadAttendanceTrackingReport");
    if (loadBtn) {
      loadBtn.addEventListener("click", loadReport);
    }

    const exportBtn = document.getElementById("exportAttendanceTrackingReport");
    if (exportBtn) {
      exportBtn.addEventListener("click", exportReport);
    }

    const courseFilter = document.getElementById("attendanceTrackingFilterCourse");
    if (courseFilter) {
      courseFilter.addEventListener("change", applyFilters);
    }

    const profFilter = document.getElementById("attendanceTrackingFilterProfessor");
    if (profFilter) {
      profFilter.addEventListener("change", () => {
        updateCourseDropdown(); // Update course dropdown first
        applyFilters();          // Then apply filters
      });
    }

    const statusFilter = document.getElementById("attendanceTrackingFilterStatus");
    if (statusFilter) {
      statusFilter.addEventListener("change", applyFilters);
    }
  }

  // Auto-init when DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAttendanceTracking);
  } else {
    initAttendanceTracking();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAttendanceTracking = initAttendanceTracking;
})();
