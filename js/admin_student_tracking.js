/**
 * Admin Panel - Student Attendance Tracking
 */

(function () {
  'use strict';

  console.log('[Student Tracking] Script loaded');

  let allTerms = [];
  let allWeeks = [];
  let allStudents = [];
  let allCourses = [];

  function init() {
    console.log('[Student Tracking] Initializing...');
    loadTerms();
    setupEventListeners();
  }

  function loadTerms() {
    console.log('[Student Tracking] Loading terms...');
    fetch('php/get_terms.php')
      .then(r => r.json())
      .then(data => {
        console.log('[Student Tracking] Terms loaded:', data);
        if (data.success && Array.isArray(data.data)) {
          allTerms = data.data;
          populateTermDropdown();
        }
      })
      .catch(err => console.error('[Student Tracking] Failed to load terms:', err));
  }

  function populateTermDropdown() {
    const sel = document.getElementById('studentTrackingTermFilter');
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
      .catch(err => console.error('Failed to load weeks:', err));
  }

  function populateWeekDropdowns() {
    const fromSel = document.getElementById('studentTrackingFromWeek');
    const toSel = document.getElementById('studentTrackingToWeek');
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
    const sel = document.getElementById('studentTrackingStudentFilter');
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
      .catch(err => console.error('Failed to load students:', err));
  }

  function loadCourses(yearLevel) {
    const sel = document.getElementById('studentTrackingSubjectFilter');
    if (!sel) return;

    sel.innerHTML = '<option value="all">All Subjects</option>';
    allCourses = [];

    if (!yearLevel) return;

    fetch(`php/get_courses.php?year_level=${yearLevel}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && Array.isArray(data.data)) {
          allCourses = data.data;
          allCourses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.course_id;
            opt.textContent = `${c.course_name} (${c.subject_code || ''})`;
            sel.appendChild(opt);
          });
        }
      })
      .catch(err => console.error('Failed to load courses:', err));
  }

  function setupEventListeners() {
    const yearSel = document.getElementById('studentTrackingYearFilter');
    const termSel = document.getElementById('studentTrackingTermFilter');
    const exportBtn = document.getElementById('exportStudentTrackingReport');

    if (yearSel) {
      yearSel.addEventListener('change', () => {
        const year = yearSel.value;
        if (year) {
          loadStudents(year);
          loadCourses(year);
        } else {
          document.getElementById('studentTrackingStudentFilter').innerHTML = '<option value="all">All Students in Year</option>';
          document.getElementById('studentTrackingSubjectFilter').innerHTML = '<option value="all">All Subjects</option>';
        }
      });
    }

    if (termSel) {
      termSel.addEventListener('change', () => {
        const termId = termSel.value;
        loadWeeks(termId);
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', exportReport);
    }
  }

  function exportReport() {
    const statusDiv = document.getElementById('studentTrackingStatus');
    
    const yearLevel = document.getElementById('studentTrackingYearFilter').value;
    const studentId = document.getElementById('studentTrackingStudentFilter').value;
    const courseId = document.getElementById('studentTrackingSubjectFilter').value;
    const termId = document.getElementById('studentTrackingTermFilter').value;
    const weekFrom = document.getElementById('studentTrackingFromWeek').value;
    const weekTo = document.getElementById('studentTrackingToWeek').value;

    if (!yearLevel) {
      showStatus(statusDiv, 'error', 'Please select a year level');
      return;
    }

    const params = new URLSearchParams();
    params.append('year_level', yearLevel);
    
    if (studentId && studentId !== 'all') {
      params.append('student_id', studentId);
    }
    
    if (courseId && courseId !== 'all') {
      params.append('course_id', courseId);
    }
    
    if (termId) {
      params.append('term_id', termId);
    }
    
    if (weekFrom) {
      params.append('week_id_from', weekFrom);
    }
    
    if (weekTo) {
      params.append('week_id_to', weekTo);
    }

    showStatus(statusDiv, 'info', '📥 Generating Excel file...');

    const url = `php/export_student_tracking.php?${params.toString()}`;
    window.location.href = url;

    setTimeout(() => {
      showStatus(statusDiv, 'success', '✅ Excel file generated successfully');
      setTimeout(() => {
        statusDiv.textContent = '';
        statusDiv.className = 'status';
      }, 3000);
    }, 1000);
  }

  function showStatus(div, type, message) {
    if (!div) return;
    div.textContent = message;
    div.className = 'status ' + type;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
