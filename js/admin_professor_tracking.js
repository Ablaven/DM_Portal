(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};

  let allLectures = [];
  let filteredLectures = [];

  async function loadTermsAndWeeks() {
    console.log("[Professor Tracking] Starting initialization...");
    try {
      // Load terms first
      const termsPayload = await fetchJson("php/get_terms.php");
      if (!termsPayload?.success) {
        console.error("Failed to load terms:", termsPayload?.error);
        return;
      }

      const terms = termsPayload.data || [];
      console.log("[Professor Tracking] Loaded", terms.length, "terms");
      
      const termSelect = document.getElementById("professorTrackingTermFilter");
      if (termSelect) {
        termSelect.innerHTML = '<option value="">All Terms</option>';
        let activeTermId = null;
        
        for (const term of terms) {
          const option = document.createElement("option");
          option.value = term.term_id;
          
          // Build label with academic year info
          let label = term.label || `Semester ${term.semester}`;
          
          if (term.academic_year_label) {
            label += ` - ${term.academic_year_label}`;
          } else if (term.academic_year_id) {
            label += ` (Year ${term.academic_year_id})`;
          }
          
          if (term.status === 'active') {
            label += ' (Active)';
            activeTermId = term.term_id;
          }
          
          option.textContent = label;
          termSelect.appendChild(option);
        }
        
        // Auto-select active term
        if (activeTermId) {
          termSelect.value = activeTermId;
          console.log("[Professor Tracking] Auto-selected active term:", activeTermId);
        }
      }

      // Load weeks for all terms
      let allWeeks = [];
      for (const term of terms) {
        const payload = await fetchJson(`php/get_weeks.php?term_id=${term.term_id}`);
        if (payload.success && payload.data) {
          const weeksWithTerm = payload.data.map(w => ({ ...w, term_id: term.term_id }));
          allWeeks.push(...weeksWithTerm);
        }
      }
      
      allWeeks.sort((a, b) => a.week_id - b.week_id);
      console.log("[Professor Tracking] Loaded", allWeeks.length, "total weeks");
      
      // Store weeks globally
      window._professorTrackingWeeks = allWeeks;
      
      // Populate and filter week dropdowns
      filterWeeksByTerm(allWeeks, termSelect?.value || "");
      
      // Setup term filter listener
      if (termSelect) {
        termSelect.addEventListener("change", () => {
          filterWeeksByTerm(allWeeks, termSelect.value);
        });
      }
      
      console.log("[Professor Tracking] ✅ Initialization complete!");
    } catch (err) {
      console.error("[Professor Tracking] ❌ Error:", err);
    }
  }

  function filterWeeksByTerm(weeks, selectedTermId) {
    const fromSelect = document.getElementById("professorTrackingFromWeek");
    const toSelect = document.getElementById("professorTrackingToWeek");

    if (!fromSelect || !toSelect) return;

    // Filter weeks by selected term
    const filteredWeeks = selectedTermId 
      ? weeks.filter(w => String(w.term_id) === String(selectedTermId))
      : weeks;

    console.log("[Professor Tracking] Filtering:", filteredWeeks.length, "weeks for term:", selectedTermId);

    // Repopulate week selectors
    fromSelect.innerHTML = '<option value="">Select week…</option>';
    toSelect.innerHTML = '<option value="">Select week…</option>';

    for (const week of filteredWeeks) {
      const option1 = document.createElement("option");
      option1.value = week.week_id;
      option1.textContent = week.label || `Week ${week.week_id}`;

      const option2 = document.createElement("option");
      option2.value = week.week_id;
      option2.textContent = week.label || `Week ${week.week_id}`;

      fromSelect.appendChild(option1);
      toSelect.appendChild(option2);
    }
  }

  async function loadReport() {
    const fromWeekId = parseInt(document.getElementById("professorTrackingFromWeek")?.value || "0", 10);
    const toWeekId = parseInt(document.getElementById("professorTrackingToWeek")?.value || "0", 10);

    if (!fromWeekId || !toWeekId) {
      setStatusById("professorTrackingStatus", "Please select both 'From Week' and 'To Week'.", "error");
      return;
    }

    if (fromWeekId > toWeekId) {
      setStatusById("professorTrackingStatus", "'From Week' must be less than or equal to 'To Week'.", "error");
      return;
    }

    setStatusById("professorTrackingStatus", "Loading professor attendance report…", "");

    try {
      const payload = await fetchJson(
        `php/get_professor_tracking_report.php?week_id_from=${fromWeekId}&week_id_to=${toWeekId}`
      );

      if (!payload?.success) {
        throw new Error(payload?.error || "Failed to load report.");
      }

      allLectures = payload.data?.lectures || [];
      const summary = payload.data?.summary || {};

      // Update summary stats
      document.getElementById("professorTrackingTotalLectures").textContent = summary.total_lectures || 0;
      document.getElementById("professorTrackingPresent").textContent = summary.professor_present || 0;
      document.getElementById("professorTrackingAbsent").textContent = summary.professor_absent || 0;
      document.getElementById("professorTrackingCanceled").textContent = summary.canceled || 0;
      document.getElementById("professorTrackingRate").textContent = (summary.attendance_rate || 0) + "%";

      // Show summary
      const summaryEl = document.getElementById("professorTrackingSummary");
      if (summaryEl) {
        summaryEl.style.display = "block";
      }

      // Populate filter dropdowns
      populateFilterDropdowns();

      // Apply filters and render table
      applyFilters();

      // Show table
      const tableWrap = document.getElementById("professorTrackingTableWrap");
      if (tableWrap) {
        tableWrap.style.display = "block";
      }

      // Enable export button
      const exportBtn = document.getElementById("exportProfessorTrackingReport");
      if (exportBtn) {
        exportBtn.disabled = false;
      }

      setStatusById("professorTrackingStatus", `Loaded ${allLectures.length} lectures.`, "success");
    } catch (err) {
      setStatusById("professorTrackingStatus", err.message || "Failed to load report.", "error");
    }
  }

  function populateFilterDropdowns() {
    const courses = [...new Set(allLectures.map((l) => l.course_name))].sort();
    const professors = [...new Set(allLectures.map((l) => l.doctor_name))].sort();

    const courseSelect = document.getElementById("professorTrackingFilterCourse");
    const profSelect = document.getElementById("professorTrackingFilterProfessor");

    if (courseSelect) {
      courseSelect.innerHTML = '<option value="">All Courses</option>';
      courses.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c;
        opt.textContent = c;
        courseSelect.appendChild(opt);
      });
    }

    if (profSelect) {
      profSelect.innerHTML = '<option value="">All Professors</option>';
      professors.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p;
        opt.textContent = p;
        profSelect.appendChild(opt);
      });
    }
  }

  function applyFilters() {
    const selectedCourse = document.getElementById("professorTrackingFilterCourse")?.value || "";
    const selectedProfessor = document.getElementById("professorTrackingFilterProfessor")?.value || "";
    const selectedStatus = document.getElementById("professorTrackingFilterStatus")?.value || "";

    filteredLectures = allLectures.filter((lec) => {
      if (selectedCourse && lec.course_name !== selectedCourse) return false;
      if (selectedProfessor && lec.doctor_name !== selectedProfessor) return false;

      if (selectedStatus === "present" && lec.teacher_attendance !== "Yes") return false;
      if (selectedStatus === "absent" && lec.teacher_attendance !== "No") return false;
      if (selectedStatus === "canceled" && lec.teacher_attendance !== "Canceled") return false;

      return true;
    });

    renderTable();
  }

  function renderTable() {
    const tbody = document.querySelector("#professorTrackingTable tbody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (filteredLectures.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 8;
      td.textContent = "No lectures found matching the selected filters.";
      td.style.textAlign = "center";
      td.style.padding = "20px";
      td.className = "muted";
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    filteredLectures.forEach((lec) => {
      const tr = document.createElement("tr");

      // Determine row styling based on teacher attendance
      let attendanceClass = "";
      if (lec.teacher_attendance === "Yes") {
        attendanceClass = "status-present";
      } else if (lec.teacher_attendance === "No") {
        attendanceClass = "status-absent";
      }

      tr.innerHTML = `
        <td>${escapeHtml(lec.week_label)}</td>
        <td>${escapeHtml(lec.date)}</td>
        <td>${escapeHtml(lec.day)}</td>
        <td style="white-space:nowrap;">${escapeHtml(lec.time)}</td>
        <td>${escapeHtml(lec.course_name)}</td>
        <td>${escapeHtml(lec.doctor_name)}</td>
        <td>${escapeHtml(lec.status)}</td>
        <td class="${attendanceClass}" style="font-weight:600;">${escapeHtml(lec.teacher_attendance)}</td>
      `;

      tbody.appendChild(tr);
    });
  }

  function exportReport() {
    const fromWeekId = parseInt(document.getElementById("professorTrackingFromWeek")?.value || "0", 10);
    const toWeekId = parseInt(document.getElementById("professorTrackingToWeek")?.value || "0", 10);

    if (!fromWeekId || !toWeekId) {
      setStatusById("professorTrackingStatus", "Please select both weeks before exporting.", "error");
      return;
    }

    window.location.href = `php/export_professor_tracking.php?week_id_from=${fromWeekId}&week_id_to=${toWeekId}`;
  }

  function init() {
    console.log("[Professor Tracking] init() called");
    
    // Delay loading to let attendance tracking populate first
    setTimeout(() => {
      loadTermsAndWeeks();
    }, 500);

    const loadBtn = document.getElementById("loadProfessorTrackingReport");
    console.log("[Professor Tracking] Load button found:", !!loadBtn);
    if (loadBtn) {
      loadBtn.addEventListener("click", loadReport);
    }

    const exportBtn = document.getElementById("exportProfessorTrackingReport");
    console.log("[Professor Tracking] Export button found:", !!exportBtn);
    if (exportBtn) {
      exportBtn.addEventListener("click", exportReport);
    }

    // Filter change listeners
    document.getElementById("professorTrackingFilterCourse")?.addEventListener("change", applyFilters);
    document.getElementById("professorTrackingFilterProfessor")?.addEventListener("change", applyFilters);
    document.getElementById("professorTrackingFilterStatus")?.addEventListener("change", applyFilters);
  }

  if (typeof window !== "undefined") {
    window.dmportal = window.dmportal || {};
    window.dmportal.initProfessorTracking = init;
  }
})();
