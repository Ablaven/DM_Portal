/**
 * Admin Panel - Student Attendance Summary
 * Exports attendance percentage by student and subject
 */

(function () {
  'use strict';

  console.log('[Attendance Summary] Script loaded');

  let allTerms = [];
  let allWeeks = [];
  let allStudents = [];
  let allCourses = [];

  function init() {
    console.log('[Attendance Summary] Initializing...');
    loadTerms();
    setupEventListeners();
  }

  function loadTerms() {
    console.log('[Attendance Summary] Loading terms...');
    fetch('php/get_terms.php')
      .then(r => r.json())
      .then(data => {
        console.log('[Attendance Summary] Terms loaded:', data);
        if (data.success && Array.isArray(data.data)) {
          allTerms = data.data;
          populateTermDropdown();
        }
      })
      .catch(err => console.error('[Attendance Summary] Failed to load terms:', err));
  }

  function populateTermDropdown() {
    const sel = document.getElementById('summaryTermFilter');
    if (!sel) return;

    sel.innerHTML = '<option value="">All Terms</option>';

    let activeTermId = null;
    allTerms.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.term_id;
      
      // Build label with academic year info
      let label = t.label || `Semester ${t.semester}`;
      
      if (t.academic_year_label) {
        label += ` - ${t.academic_year_label}`;
      } else if (t.academic_year_id) {
        label += ` (Year ${t.academic_year_id})`;
      }
      
      if (t.status === 'active') {
        label += ' (Active)';
        opt.selected = true;
        activeTermId = t.term_id;
      }
      
      opt.textContent = label;
      sel.appendChild(opt);
    });
    
    // Load weeks for the active term
    if (activeTermId) {
      loadWeeks(activeTermId);
    }
  }

  function loadWeeks(termId) {
    if (!termId) {
      allWeeks = [];
      populateWeekDropdowns();
      return;
    }

    fetch(`php/get_weeks.php?term_id=${termId}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && Array.isArray(data.data)) {
          allWeeks = data.data;
          // Sort weeks by week_id ascending (earliest first)
          allWeeks.sort((a, b) => a.week_id - b.week_id);
          populateWeekDropdowns();
        }
      })
      .catch(err => console.error('[Attendance Summary] Failed to load weeks:', err));
  }

  function populateWeekDropdowns() {
    const fromSel = document.getElementById('summaryFromWeek');
    const toSel = document.getElementById('summaryToWeek');
    if (!fromSel || !toSel) return;

    fromSel.innerHTML = '<option value="">Select week…</option>';
    toSel.innerHTML = '<option value="">Select week…</option>';

    allWeeks.forEach(w => {
      const optFrom = document.createElement('option');
      optFrom.value = w.week_id;
      optFrom.textContent = w.label || `Week ${w.week_id}`;
      fromSel.appendChild(optFrom);

      const optTo = document.createElement('option');
      optTo.value = w.week_id;
      optTo.textContent = w.label || `Week ${w.week_id}`;
      toSel.appendChild(optTo);
    });

    if (allWeeks.length > 0) {
      fromSel.value = allWeeks[0].week_id;  // First week
      toSel.value = allWeeks[allWeeks.length - 1].week_id;  // Last week
    }
  }

  function loadStudents(yearLevel) {
    const sel = document.getElementById('summaryStudentFilter');
    if (!sel) return;

    sel.innerHTML = '<option value="all">All Students in Year</option>';
    allStudents = [];

    if (!yearLevel) return;

    fetch(`php/get_students_by_year.php?year_level=${yearLevel}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && Array.isArray(data.students)) {
          allStudents = data.students;
          allStudents.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.student_id;
            opt.textContent = `${s.full_name} (${s.student_code || 'MG' + s.student_id})`;
            sel.appendChild(opt);
          });
        }
      })
      .catch(err => console.error('[Attendance Summary] Failed to load students:', err));
  }

  function loadCourses(yearLevel) {
    const sel = document.getElementById('summarySubjectFilter');
    if (!sel) return;

    sel.innerHTML = '<option value="all">All Subjects</option>';
    allCourses = [];

    if (!yearLevel) return;

    fetch(`php/get_courses.php?year_level=${yearLevel}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && Array.isArray(data.courses)) {
          allCourses = data.courses;
          allCourses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.course_id;
            opt.textContent = `${c.course_name} (${c.subject_code})`;
            sel.appendChild(opt);
          });
        }
      })
      .catch(err => console.error('[Attendance Summary] Failed to load courses:', err));
  }

  function setupEventListeners() {
    // Year level change
    const yearSel = document.getElementById('summaryYearFilter');
    if (yearSel) {
      yearSel.addEventListener('change', function () {
        const year = this.value;
        if (year) {
          loadStudents(parseInt(year, 10));
          loadCourses(parseInt(year, 10));
        } else {
          document.getElementById('summaryStudentFilter').innerHTML = '<option value="all">All Students in Year</option>';
          document.getElementById('summarySubjectFilter').innerHTML = '<option value="all">All Subjects</option>';
        }
      });
    }

    // Term change
    const termSel = document.getElementById('summaryTermFilter');
    if (termSel) {
      termSel.addEventListener('change', function () {
        const termId = this.value;
        if (termId) {
          loadWeeks(parseInt(termId, 10));
        } else {
          allWeeks = [];
          populateWeekDropdowns();
        }
      });
    }

    // Export button
    const exportBtn = document.getElementById('exportAttendanceSummary');
    if (exportBtn) {
      exportBtn.addEventListener('click', handleExport);
    }
  }

  function handleExport() {
    console.log('[Attendance Summary] Export button clicked');
    const statusDiv = document.getElementById('attendanceSummaryStatus');
    const yearLevel = document.getElementById('summaryYearFilter').value;
    const studentId = document.getElementById('summaryStudentFilter').value;
    const courseId = document.getElementById('summarySubjectFilter').value;
    const termId = document.getElementById('summaryTermFilter').value;
    const weekFrom = document.getElementById('summaryFromWeek').value;
    const weekTo = document.getElementById('summaryToWeek').value;

    console.log('[Attendance Summary] Filters:', { yearLevel, studentId, courseId, termId, weekFrom, weekTo });

    // Validation
    if (!yearLevel) {
      setStatus(statusDiv, 'Please select a Year Level.', 'error');
      return;
    }

    // Build query params
    const params = new URLSearchParams();
    params.append('year_level', yearLevel);
    if (studentId && studentId !== 'all') params.append('student_id', studentId);
    if (courseId && courseId !== 'all') params.append('course_id', courseId);
    if (termId) params.append('term_id', termId);
    if (weekFrom) params.append('week_id_from', weekFrom);
    if (weekTo) params.append('week_id_to', weekTo);

    setStatus(statusDiv, 'Generating Excel file...', 'loading');

    // Trigger download
    const url = `php/export_attendance_summary.php?${params.toString()}`;
    console.log('[Attendance Summary] Export URL:', url);
    window.location.href = url;

    // Clear status after a delay
    setTimeout(() => {
      setStatus(statusDiv, 'Export started. Check your downloads.', 'success');
      setTimeout(() => {
        statusDiv.textContent = '';
        statusDiv.className = 'status';
      }, 3000);
    }, 1000);
  }

  function setStatus(el, msg, type) {
    if (!el) return;
    el.textContent = msg;
    el.className = 'status ' + type;
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
