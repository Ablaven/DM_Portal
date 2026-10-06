(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};

  let allLectures = [];
  let filteredLectures = [];

  async function loadTermsAndWeeks() {
    console.log("[Professor Tracking] Starting initialization...");
    try {
      // Check if attendance tracking has already loaded terms/weeks
      const attendanceTermSelect = document.getElementById("attendanceTrackingTermFilter");
      if (attendanceTermSelect && attendanceTermSelect.options.length > 1) {
        console.log("[Professor Tracking] Copying from attendance tracking...");
        
        // Copy terms
        const termSelect = document.getElementById("professorTrackingTermFilter");
        if (termSelect && attendanceTermSelect) {
          termSelect.innerHTML = attendanceTermSelect.innerHTML;
          // Auto-select the same active term
          termSelect.value = attendanceTermSelect.value;
          console.log("[Professor Tracking] Copied", termSelect.options.length - 1, "terms");
          console.log("[Professor Tracking] Auto-selected term:", termSelect.value);
        }
        
        // Copy weeks
        const attendanceFromWeek = document.getElementById("attendanceTrackingFromWeek");
        const professorFromWeek = document.getElementById("professorTrackingFromWeek");
        if (attendanceFromWeek && professorFromWeek) {
          professorFromWeek.innerHTML = attendanceFromWeek.innerHTML;
          console.log("[Professor Tracking] Copied", professorFromWeek.options.length - 1, "weeks to From");
        }
        
        const attendanceToWeek = document.getElementById("attendanceTrackingToWeek");
        const professorToWeek = document.getElementById("professorTrackingToWeek");
        if (attendanceToWeek && professorToWeek) {
          professorToWeek.innerHTML = attendanceToWeek.innerHTML;
          console.log("[Professor Tracking] Copied", professorToWeek.options.length - 1, "weeks to To");
        }
        
        console.log("[Professor Tracking] ✅ Initialization complete (copied from attendance tracking)!");
        return;
      }
      
      // Otherwise load independently
      const termsPayload = await fetchJson("php/get_terms.php");
      if (!termsPayload?.success) {
        console.error("Failed to load terms:", termsPayload?.error);
        return;
      }

      const terms = termsPayload.data?.terms || [];
      console.log("[Professor Tracking] Loaded", terms.length, "terms");
      const termSelect = document.getElementById("professorTrackingTermFilter");
      console.log("[Professor Tracking] Term select found:", !!termSelect);
      if (termSelect) {
        termSelect.innerHTML = '<option value="">All Terms</option>';
        let activeTermId = null;
        terms.forEach((t) => {
          const opt = document.createElement("option");
          opt.value = t.term_id;
          opt.textContent = t.label || `Semester ${t.semester_number}`;
          termSelect.appendChild(opt);
          
          // Check if this is the active term
          if (t.is_active === 1 || t.is_active === "1" || t.is_active === true) {
            activeTermId = t.term_id;
          }
        });
        
        // Auto-select the active term
        if (activeTermId) {
          termSelect.value = activeTermId;
          console.log("[Professor Tracking] Auto-selected active term:", activeTermId);
        }
      }

      // Load all weeks
      const weeksPayload = await fetchJson("php/get_weeks.php");
      if (!weeksPayload?.success) {
        console.error("Failed to load weeks:", weeksPayload?.error);
        return;
      }

      const weeks = weeksPayload.data?.weeks || [];
      console.log("[Professor Tracking] Loaded", weeks.length, "weeks");
      
      // Store weeks globally for filtering
      window._professorTrackingWeeks = weeks;
      
      populateWeekDropdowns(weeks);
      console.log("[Professor Tracking] Week dropdowns populated");

      // Setup term filter listener
      if (termSelect) {
        termSelect.addEventListener("change", () => filterWeeksByTerm(weeks));
        
        // Trigger filter if we auto-selected an active term
        if (termSelect.value) {
          filterWeeksByTerm(weeks);
          console.log("[Professor Tracking] Filtered weeks by active term");
        }
      }
      
      console.log("[Professor Tracking] ✅ Initialization complete!");
    } catch (err) {
      console.error("[Professor Tracking] ❌ Error:", err);
    }
  }

  function populateWeekDropdowns(weeks) {
    const fromSelect = document.getElementById("professorTrackingFromWeek");
    const toSelect = document.getElementById("professorTrackingToWeek");

    if (!fromSelect || !toSelect) return;

    fromSelect.innerHTML = '<option value="">Select week…</option>';
    toSelect.innerHTML = '<option value="">Select week…</option>';

    weeks.forEach((w) => {
      const opt1 = document.createElement("option");
      opt1.value = w.week_id;
      opt1.textContent = w.label;
      opt1.dataset.termId = w.term_id || "";

      const opt2 = document.createElement("option");
      opt2.value = w.week_id;
      opt2.textContent = w.label;
      opt2.dataset.termId = w.term_id || "";

      fromSelect.appendChild(opt1);
      toSelect.appendChild(opt2);
    });
  }

  function filterWeeksByTerm(weeks) {
    const selectedTermId = document.getElementById("professorTrackingTermFilter")?.value || "";
    const fromSelect = document.getElementById("professorTrackingFromWeek");
    const toSelect = document.getElementById("professorTrackingToWeek");

    if (!fromSelect || !toSelect) return;

    const currentFrom = fromSelect.value;
    const currentTo = toSelect.value;

    fromSelect.innerHTML = '<option value="">Select week…</option>';
    toSelect.innerHTML = '<option value="">Select week…</option>';

    weeks.forEach((w) => {
      const termId = String(w.term_id || "");
      if (!selectedTermId || termId === selectedTermId) {
        const opt1 = document.createElement("option");
        opt1.value = w.week_id;
        opt1.textContent = w.label;
        opt1.dataset.termId = termId;

        const opt2 = document.createElement("option");
        opt2.value = w.week_id;
        opt2.textContent = w.label;
        opt2.dataset.termId = termId;

        fromSelect.appendChild(opt1);
        toSelect.appendChild(opt2);
      }
    });

    // Restore selections if still valid
    if (currentFrom && fromSelect.querySelector(`option[value="${currentFrom}"]`)) {
      fromSelect.value = currentFrom;
    }
    if (currentTo && toSelect.querySelector(`option[value="${currentTo}"]`)) {
      toSelect.value = currentTo;
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
