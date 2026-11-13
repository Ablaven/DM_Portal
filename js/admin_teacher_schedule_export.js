(function () {
  "use strict";

  const { fetchJson, setStatusById } = window.dmportal || {};

  async function loadTermsAndWeeks() {
    console.log("[Teacher Schedule Export] Starting initialization...");
    try {
      // Load terms first
      const termsPayload = await fetchJson("php/get_terms.php");
      if (!termsPayload?.success) {
        console.error("Failed to load terms:", termsPayload?.error);
        return;
      }

      const terms = termsPayload.data || [];
      console.log("[Teacher Schedule Export] Loaded", terms.length, "terms");
      
      const termSelect = document.getElementById("teacherScheduleTermFilter");
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
          console.log("[Teacher Schedule Export] Auto-selected active term:", activeTermId);
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
      console.log("[Teacher Schedule Export] Loaded", allWeeks.length, "total weeks");
      
      // Store weeks globally
      window._teacherScheduleWeeks = allWeeks;
      
      // Load all teachers
      await loadTeachers();
      
      // Populate and filter week dropdowns
      filterWeeksByTerm(allWeeks, termSelect?.value || "");
      
      // Setup term filter listener
      if (termSelect) {
        termSelect.addEventListener("change", () => {
          filterWeeksByTerm(allWeeks, termSelect.value);
        });
      }
      
      console.log("[Teacher Schedule Export] ✅ Initialization complete!");
    } catch (err) {
      console.error("[Teacher Schedule Export] ❌ Error:", err);
    }
  }

  async function loadTeachers() {
    try {
      const payload = await fetchJson("php/get_doctors.php");
      if (!payload?.success) {
        console.error("Failed to load teachers:", payload?.error);
        return;
      }

      const teachers = payload.data || [];
      console.log("[Teacher Schedule Export] Loaded", teachers.length, "teachers");
      
      // Store teachers globally
      window._allTeachers = teachers;
      
      // Initial population
      populateTeacherDropdown(teachers, "All");
      
      // Setup nationality filter listener
      const nationalitySelect = document.getElementById("teacherNationalityFilter");
      
      if (nationalitySelect) {
        nationalitySelect.addEventListener("change", () => {
          const nationality = nationalitySelect.value;
          populateTeacherDropdown(teachers, nationality);
        });
      }
    } catch (err) {
      console.error("[Teacher Schedule Export] Error loading teachers:", err);
    }
  }

  function populateTeacherDropdown(teachers, nationality) {
    const teacherSelect = document.getElementById("teacherSelectFilter");
    if (!teacherSelect) return;

    // Filter teachers by nationality
    let filteredTeachers = teachers;
    if (nationality === "French") {
      filteredTeachers = teachers.filter(t => t.doctor_type === "French");
    } else if (nationality === "Egyptian") {
      filteredTeachers = teachers.filter(t => t.doctor_type === "Egyptian");
    }

    // Clear and repopulate
    teacherSelect.innerHTML = '<option value="All">All Teachers (from selected nationality)</option>';
    
    // Sort by name
    filteredTeachers.sort((a, b) => {
      const nameA = (a.full_name || "").toLowerCase();
      const nameB = (b.full_name || "").toLowerCase();
      return nameA.localeCompare(nameB);
    });
    
    // Add teachers
    for (const teacher of filteredTeachers) {
      const opt = document.createElement("option");
      opt.value = String(teacher.doctor_id);
      opt.textContent = teacher.full_name + (teacher.doctor_type ? ` (${teacher.doctor_type})` : '');
      teacherSelect.appendChild(opt);
    }
    
    console.log("[Teacher Schedule Export] Populated", filteredTeachers.length, "teachers for nationality:", nationality);
  }

  function filterWeeksByTerm(weeks, selectedTermId) {
    const fromSelect = document.getElementById("teacherScheduleFromWeek");
    const toSelect = document.getElementById("teacherScheduleToWeek");

    if (!fromSelect || !toSelect) return;

    // Filter weeks by selected term
    const filteredWeeks = selectedTermId 
      ? weeks.filter(w => String(w.term_id) === String(selectedTermId))
      : weeks;

    console.log("[Teacher Schedule Export] Filtering:", filteredWeeks.length, "weeks for term:", selectedTermId);

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

  async function exportSchedule() {
    const fromWeekId = parseInt(document.getElementById("teacherScheduleFromWeek")?.value || "0", 10);
    const toWeekId = parseInt(document.getElementById("teacherScheduleToWeek")?.value || "0", 10);
    const nationalityFilter = document.getElementById("teacherNationalityFilter")?.value || "All";
    const teacherId = document.getElementById("teacherSelectFilter")?.value || "All";

    if (!fromWeekId || !toWeekId) {
      setStatusById("teacherScheduleStatus", "Please select both 'From Week' and 'To Week'.", "error");
      return;
    }

    if (fromWeekId > toWeekId) {
      setStatusById("teacherScheduleStatus", "'From Week' must be less than or equal to 'To Week'.", "error");
      return;
    }

    setStatusById("teacherScheduleStatus", "Generating Excel file, please wait…", "");

    try {
      // Build download URL
      let url = `php/export_teacher_schedules_excel.php?week_id_from=${fromWeekId}&week_id_to=${toWeekId}&nationality=${encodeURIComponent(nationalityFilter)}`;
      
      // Add teacher_id if specific teacher selected
      if (teacherId !== "All") {
        url += `&teacher_id=${encodeURIComponent(teacherId)}`;
      }
      
      // Trigger download
      const link = document.createElement("a");
      link.href = url;
      link.download = `Teachers_Schedule_Week${fromWeekId}-${toWeekId}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      setStatusById("teacherScheduleStatus", "Excel file downloaded successfully!", "success");
      
      // Clear status after 3 seconds
      setTimeout(() => {
        setStatusById("teacherScheduleStatus", "", "");
      }, 3000);
    } catch (err) {
      setStatusById("teacherScheduleStatus", err.message || "Failed to export schedule.", "error");
    }
  }

  function initTeacherScheduleExport() {
    console.log("[Teacher Schedule Export] Initializing...");
    
    // Load terms and weeks
    loadTermsAndWeeks();

    // Setup export button
    const exportBtn = document.getElementById("exportTeacherSchedule");
    if (exportBtn) {
      exportBtn.addEventListener("click", exportSchedule);
    }
  }

  // Expose to global scope
  if (window.dmportal) {
    window.dmportal.initTeacherScheduleExport = initTeacherScheduleExport;
  } else {
    window.dmportal = { initTeacherScheduleExport };
  }
})();
