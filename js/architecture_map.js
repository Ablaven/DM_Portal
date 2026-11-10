/* ============================================
   ARCHITECTURE MAP - MODERN CLEAN VERSION
   ============================================ */

(function () {
  'use strict';

  const { escapeHtml } = window.dmportal || {};

  // ============================================
  // DATA MODELS
  // ============================================

  const DATABASE_TABLES = [
    { name: 'portal_users', module: 'Authentication', columns: ['user_id', 'username', 'password_hash', 'role', 'doctor_id', 'student_id', 'allowed_pages_json', 'is_active'], fks: ['doctor_id → doctors', 'student_id → students'] },
    { name: 'doctors', module: 'Staff', columns: ['doctor_id', 'full_name', 'email', 'doctor_type', 'phone', 'color_code'], fks: [] },
    { name: 'students', module: 'Academic', columns: ['student_id', 'student_code', 'full_name', 'email', 'year_level', 'semester', 'program'], fks: [] },
    { name: 'courses', module: 'Academic', columns: ['course_id', 'course_name', 'course_type', 'subject_code', 'year_level', 'semester', 'total_hours'], fks: [] },
    { name: 'academic_years', module: 'Academic', columns: ['academic_year_id', 'label', 'start_date', 'end_date', 'is_active'], fks: [] },
    { name: 'terms', module: 'Academic', columns: ['term_id', 'academic_year_id', 'label', 'semester', 'is_active'], fks: ['academic_year_id → academic_years'] },
    { name: 'weeks', module: 'Scheduling', columns: ['week_id', 'term_id', 'label', 'start_date', 'end_date', 'status', 'week_type', 'is_ramadan'], fks: ['term_id → terms'] },
    { name: 'doctor_schedules', module: 'Scheduling', columns: ['schedule_id', 'week_id', 'doctor_id', 'course_id', 'day_of_week', 'slot_number', 'room_code', 'extra_time', 'counts_towards_hours'], fks: ['week_id → weeks', 'doctor_id → doctors', 'course_id → courses'] },
    { name: 'availability', module: 'Scheduling', columns: ['availability_id', 'doctor_id', 'week_id', 'day_of_week', 'slot_number'], fks: ['doctor_id → doctors', 'week_id → weeks'] },
    { name: 'unavailability', module: 'Scheduling', columns: ['id', 'doctor_id', 'start_datetime', 'end_datetime', 'reason', 'created_at'], fks: ['doctor_id → doctors'] },
    { name: 'doctor_day_cancellations', module: 'Scheduling', columns: ['cancellation_id', 'doctor_id', 'week_id', 'day_of_week', 'reason'], fks: ['doctor_id → doctors', 'week_id → weeks'] },
    { name: 'doctor_slot_cancellations', module: 'Scheduling', columns: ['cancellation_id', 'doctor_id', 'week_id', 'day_of_week', 'slot_number', 'reason'], fks: ['doctor_id → doctors', 'week_id → weeks'] },
    { name: 'attendance_sessions', module: 'Attendance', columns: ['session_id', 'schedule_id', 'opened_at', 'closed_at', 'hours_counted'], fks: ['schedule_id → doctor_schedules'] },
    { name: 'attendance_records', module: 'Attendance', columns: ['attendance_id', 'session_id', 'student_id', 'status', 'recorded_at'], fks: ['session_id → attendance_sessions', 'student_id → students'] },
    { name: 'attendance_qr_sessions', module: 'Attendance', columns: ['session_id', 'schedule_id', 'token', 'expires_at', 'created_at', 'is_closed'], fks: ['schedule_id → doctor_schedules'] },
    { name: 'evaluation_categories', module: 'Evaluation', columns: ['category_id', 'category_key', 'label', 'description'], fks: [] },
    { name: 'evaluation_configs', module: 'Evaluation', columns: ['config_id', 'course_id', 'doctor_id', 'term_id', 'created_at'], fks: ['course_id → courses', 'doctor_id → doctors', 'term_id → terms'] },
    { name: 'evaluation_config_items', module: 'Evaluation', columns: ['item_id', 'config_id', 'category_key', 'weight', 'max_score'], fks: ['config_id → evaluation_configs'] },
    { name: 'evaluation_grades', module: 'Evaluation', columns: ['grade_id', 'config_id', 'student_id', 'final_grade', 'letter_grade', 'is_finalized'], fks: ['config_id → evaluation_configs', 'student_id → students'] },
    { name: 'evaluation_grade_items', module: 'Evaluation', columns: ['grade_item_id', 'grade_id', 'item_id', 'score'], fks: ['grade_id → evaluation_grades', 'item_id → evaluation_config_items'] },
    { name: 'assignments', module: 'Assignments', columns: ['assignment_id', 'course_id', 'doctor_id', 'title', 'description', 'deadline', 'year_level', 'semester'], fks: ['course_id → courses', 'doctor_id → doctors'] },
    { name: 'assignment_files', module: 'Assignments', columns: ['file_id', 'assignment_id', 'filename', 'file_path', 'uploaded_at'], fks: ['assignment_id → assignments'] },
    { name: 'assignment_submissions', module: 'Assignments', columns: ['submission_id', 'assignment_id', 'student_id', 'submitted_at', 'grade', 'feedback'], fks: ['assignment_id → assignments', 'student_id → students'] },
    { name: 'submission_files', module: 'Assignments', columns: ['file_id', 'submission_id', 'filename', 'file_path', 'uploaded_at'], fks: ['submission_id → assignment_submissions'] },
    { name: 'lecture_materials', module: 'Lectures', columns: ['material_id', 'course_id', 'doctor_id', 'term_id', 'title', 'filename', 'file_path', 'uploaded_at'], fks: ['course_id → courses', 'doctor_id → doctors', 'term_id → terms'] },
    { name: 'activity_logs', module: 'System', columns: ['log_id', 'user_id', 'username', 'role', 'action_type', 'action_category', 'target_type', 'target_id', 'details', 'old_value', 'new_value', 'ip_address', 'user_agent', 'result', 'created_at'], fks: ['user_id → portal_users'] },
    { name: 'system_settings', module: 'System', columns: ['setting_id', 'setting_key', 'setting_value', 'updated_at'], fks: [] },
    { name: 'rooms', module: 'Facilities', columns: ['room_id', 'room_code', 'room_name', 'floor_id', 'capacity'], fks: ['floor_id → floors'] },
    { name: 'floors', module: 'Facilities', columns: ['floor_id', 'floor_name', 'floor_number'], fks: [] },
    { name: 'course_doctor_hours', module: 'Scheduling', columns: ['id', 'course_id', 'doctor_id', 'allocated_hours'], fks: ['course_id → courses', 'doctor_id → doctors'] },
  ];

  const PAGES = [
    { name: 'dashboard.php', type: 'page', desc: 'Main dashboard for all users (redirects based on role)', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'student_dashboard.php', type: 'page', desc: 'Student dashboard with schedule and grades', roles: ['student'] },
    { name: 'admin_panel.php', type: 'page', desc: 'Admin control panel (users, terms, settings, activity logs)', roles: ['admin', 'management'] },
    { name: 'admin_users.php', type: 'page', desc: 'User account management', roles: ['admin'] },
    { name: 'admin_doctors.php', type: 'page', desc: 'Doctor/teacher management', roles: ['admin', 'management'] },
    { name: 'admin_students.php', type: 'page', desc: 'Student management', roles: ['admin', 'management'] },
    { name: 'admin_courses.php', type: 'page', desc: 'Course management', roles: ['admin', 'management'] },
    { name: 'architecture_map.php', type: 'page', desc: 'System architecture documentation (tables, pages, roles)', roles: ['admin'] },
    { name: 'schedule_builder.php', type: 'page', desc: 'Build weekly doctor schedules (drag-drop assignments)', roles: ['admin', 'management'] },
    { name: 'availability.php', type: 'page', desc: 'Manage doctor availability and unavailability (slot-based)', roles: ['admin', 'management', 'teacher'] },
    { name: 'doctor.php', type: 'page', desc: 'View doctor schedule', roles: ['admin', 'management', 'teacher'] },
    { name: 'students.php', type: 'page', desc: 'View student schedule', roles: ['admin', 'management', 'student'] },
    { name: 'attendance.php', type: 'page', desc: 'Mark attendance (QR code or manual, time window enforced)', roles: ['teacher'] },
    { name: 'student_qr_attendance.php', type: 'page', desc: 'Student QR attendance submission (no auth required)', roles: [] },
    { name: 'attendance_report.php', type: 'page', desc: 'Attendance reports and tracking', roles: ['admin', 'management'] },
    { name: 'evaluation.php', type: 'page', desc: 'Grade entry (weighted categories)', roles: ['teacher'] },
    { name: 'evaluation_reports.php', type: 'page', desc: 'Evaluation reports', roles: ['admin', 'management'] },
    { name: 'hours_report.php', type: 'page', desc: 'Teaching hours overview report', roles: ['admin', 'management'] },
    { name: 'hours_report_detail.php', type: 'page', desc: 'Detailed hours breakdown per doctor', roles: ['admin', 'management'] },
    { name: 'lectures.php', type: 'page', desc: 'Lecture file management (upload/download PDF/PPTX)', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'assignments.php', type: 'page', desc: 'Assignment management (teachers create, students submit)', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'profile.php', type: 'page', desc: 'User profile and password change', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'login.php', type: 'page', desc: 'Login page', roles: [] },
    
    // API Endpoints - Authentication
    { name: 'auth_login.php', type: 'api', desc: 'User login (activity logged)', roles: [] },
    { name: 'auth_logout.php', type: 'api', desc: 'User logout (activity logged)', roles: ['admin', 'management', 'teacher', 'student'] },
    
    // API Endpoints - Admin Panel
    { name: 'get_users.php', type: 'api', desc: 'Fetch user accounts', roles: ['admin'] },
    { name: 'create_user.php', type: 'api', desc: 'Create user account', roles: ['admin'] },
    { name: 'update_user.php', type: 'api', desc: 'Update user account', roles: ['admin'] },
    { name: 'delete_user.php', type: 'api', desc: 'Delete user account', roles: ['admin'] },
    { name: 'get_activity_logs.php', type: 'api', desc: 'Fetch activity logs (excludes admin role)', roles: ['admin'] },
    { name: 'export_activity_logs.php', type: 'api', desc: 'Export activity logs to Excel', roles: ['admin'] },
    
    // API Endpoints - Courses
    { name: 'get_courses.php', type: 'api', desc: 'Fetch courses', roles: ['admin', 'management', 'teacher'] },
    { name: 'add_course.php', type: 'api', desc: 'Create course', roles: ['admin', 'management'] },
    { name: 'update_course.php', type: 'api', desc: 'Update course', roles: ['admin', 'management'] },
    { name: 'delete_course.php', type: 'api', desc: 'Delete course', roles: ['admin', 'management'] },
    
    // API Endpoints - Doctors
    { name: 'get_doctors.php', type: 'api', desc: 'Fetch doctors', roles: ['admin', 'management'] },
    { name: 'add_doctor.php', type: 'api', desc: 'Create doctor', roles: ['admin', 'management'] },
    { name: 'update_doctor.php', type: 'api', desc: 'Update doctor', roles: ['admin', 'management'] },
    { name: 'delete_doctor.php', type: 'api', desc: 'Delete doctor', roles: ['admin', 'management'] },
    
    // API Endpoints - Students
    { name: 'get_students.php', type: 'api', desc: 'Fetch students', roles: ['admin', 'management'] },
    { name: 'add_student.php', type: 'api', desc: 'Create student', roles: ['admin', 'management'] },
    { name: 'update_student.php', type: 'api', desc: 'Update student', roles: ['admin', 'management'] },
    { name: 'delete_student.php', type: 'api', desc: 'Delete student', roles: ['admin', 'management'] },
    
    // API Endpoints - Schedule Builder
    { name: 'get_schedule.php', type: 'api', desc: 'Fetch weekly schedule data', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'save_schedule.php', type: 'api', desc: 'Save schedule changes', roles: ['admin', 'management'] },
    { name: 'delete_schedule_item.php', type: 'api', desc: 'Delete schedule item', roles: ['admin', 'management'] },
    
    // API Endpoints - Availability
    { name: 'get_availability.php', type: 'api', desc: 'Fetch doctor availability slots', roles: ['admin', 'management', 'teacher'] },
    { name: 'save_availability.php', type: 'api', desc: 'Save availability slot', roles: ['admin', 'management', 'teacher'] },
    { name: 'delete_availability.php', type: 'api', desc: 'Delete availability slot', roles: ['admin', 'management', 'teacher'] },
    { name: 'copy_availability.php', type: 'api', desc: 'Copy availability from previous week', roles: ['admin', 'management', 'teacher'] },
    { name: 'clear_availability.php', type: 'api', desc: 'Clear all availability for week', roles: ['admin', 'management', 'teacher'] },
    { name: 'get_unavailability.php', type: 'api', desc: 'Fetch unavailability periods', roles: ['admin', 'management', 'teacher'] },
    { name: 'add_unavailability_slot.php', type: 'api', desc: 'Add slot-based unavailability', roles: ['admin', 'management', 'teacher'] },
    { name: 'delete_unavailability.php', type: 'api', desc: 'Delete unavailability period', roles: ['admin', 'management', 'teacher'] },
    
    // API Endpoints - Attendance
    { name: 'open_attendance.php', type: 'api', desc: 'Open attendance session', roles: ['teacher'] },
    { name: 'mark_attendance.php', type: 'api', desc: 'Submit attendance records', roles: ['teacher'] },
    { name: 'get_attendance_data.php', type: 'api', desc: 'Fetch attendance records', roles: ['admin', 'management', 'teacher'] },
    { name: 'start_qr_attendance.php', type: 'api', desc: 'Start QR attendance session (first-time slot opening)', roles: ['teacher'] },
    { name: 'get_qr_token.php', type: 'api', desc: 'Get rotating QR token (auto-refreshes every 5s)', roles: ['teacher'] },
    { name: 'close_qr_session.php', type: 'api', desc: 'Close QR session (prevents reopening)', roles: ['teacher'] },
    { name: 'student_submit_qr_attendance.php', type: 'api', desc: 'Student submit attendance via QR (no auth)', roles: [] },
    { name: 'get_qr_attendance_status.php', type: 'api', desc: 'Get live QR attendance list', roles: ['teacher'] },
    
    // API Endpoints - Evaluation
    { name: 'get_evaluation_config.php', type: 'api', desc: 'Fetch evaluation config', roles: ['teacher'] },
    { name: 'save_evaluation_config.php', type: 'api', desc: 'Save evaluation config', roles: ['teacher'] },
    { name: 'save_grades.php', type: 'api', desc: 'Save student grades', roles: ['teacher'] },
    
    // API Endpoints - Assignments
    { name: 'get_assignments.php', type: 'api', desc: 'Fetch assignments', roles: ['teacher', 'student'] },
    { name: 'create_assignment.php', type: 'api', desc: 'Create assignment', roles: ['teacher'] },
    { name: 'upload_assignment_file.php', type: 'api', desc: 'Upload assignment file', roles: ['teacher'] },
    { name: 'submit_assignment.php', type: 'api', desc: 'Student submit assignment', roles: ['student'] },
    { name: 'get_assignment_submissions.php', type: 'api', desc: 'View submissions (teacher)', roles: ['teacher'] },
    
    // API Endpoints - Lectures
    { name: 'upload_lecture.php', type: 'api', desc: 'Upload lecture file', roles: ['teacher'] },
    { name: 'get_lectures.php', type: 'api', desc: 'Fetch lecture files', roles: ['teacher', 'student'] },
    { name: 'download_lecture.php', type: 'api', desc: 'Download lecture file (access controlled)', roles: ['teacher', 'student'] },
    { name: 'delete_lecture.php', type: 'api', desc: 'Delete lecture file', roles: ['teacher'] },
  ];

  // ============================================
  // INIT
  // ============================================

  function initArchitectureMap() {
    updateStats();
    renderDatabaseTables();
    renderPages();
    renderRolesTable();
    bindTabs();
    bindSearch();
  }

  // ============================================
  // STATS
  // ============================================

  function updateStats() {
    document.getElementById('statsTableCount').textContent = DATABASE_TABLES.length;
    document.getElementById('statsPageCount').textContent = PAGES.filter(p => p.type === 'page').length;
    document.getElementById('statsEndpointCount').textContent = PAGES.filter(p => p.type === 'api').length;
  }

  // ============================================
  // TABS
  // ============================================

  function bindTabs() {
    const tabs = document.querySelectorAll('.arch-tab');
    const contents = document.querySelectorAll('.arch-content');

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const targetTab = tab.dataset.tab;

        tabs.forEach(t => t.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById(`tab-${targetTab}`).classList.add('active');
      });
    });
  }

  // ============================================
  // DATABASE TABLES
  // ============================================

  function renderDatabaseTables(filter = '') {
    const container = document.getElementById('databaseTablesList');
    const filtered = filter
      ? DATABASE_TABLES.filter(t =>
          t.name.toLowerCase().includes(filter.toLowerCase()) ||
          t.module.toLowerCase().includes(filter.toLowerCase()) ||
          t.columns.some(c => c.toLowerCase().includes(filter.toLowerCase()))
        )
      : DATABASE_TABLES;

    if (!filtered.length) {
      container.innerHTML = '<div class="muted" style="text-align:center; padding:40px; grid-column: 1 / -1;">No tables found</div>';
      return;
    }

    container.innerHTML = filtered.map(table => `
      <div class="arch-table-card">
        <div class="arch-table-name">${escapeHtml(table.name)}</div>
        <div class="arch-table-module">${escapeHtml(table.module)}</div>
        <div class="arch-table-columns">
          ${table.columns.map(col => `<div>${escapeHtml(col)}</div>`).join('')}
          ${table.fks.length ? `<div class="arch-table-fk-section">${table.fks.map(fk => `<span class="arch-table-fk">${escapeHtml(fk)}</span>`).join('')}</div>` : ''}
        </div>
      </div>
    `).join('');
  }

  // ============================================
  // PAGES
  // ============================================

  function renderPages(filter = '') {
    const container = document.getElementById('pagesList');
    const filtered = filter
      ? PAGES.filter(p =>
          p.name.toLowerCase().includes(filter.toLowerCase()) ||
          p.desc.toLowerCase().includes(filter.toLowerCase())
        )
      : PAGES;

    if (!filtered.length) {
      container.innerHTML = '<div class="muted" style="text-align:center; padding:40px; grid-column: 1 / -1;">No pages found</div>';
      return;
    }

    container.innerHTML = filtered.map(page => `
      <div class="arch-page-card">
        <div class="arch-page-name">
          ${escapeHtml(page.name)}
          <span class="arch-page-type ${page.type}">${page.type.toUpperCase()}</span>
        </div>
        <div class="arch-page-desc">${escapeHtml(page.desc)}</div>
        <div class="arch-page-roles">
          ${page.roles.map(role => `<span class="arch-role-badge ${role}">${escapeHtml(role)}</span>`).join('')}
        </div>
      </div>
    `).join('');
  }

  // ============================================
  // ROLES TABLE
  // ============================================

  function renderRolesTable() {
    const tbody = document.getElementById('rolesTableBody');
    const roles = ['admin', 'management', 'teacher', 'student'];

    const rows = PAGES
      .filter(p => p.type === 'page')
      .map(page => {
        const cells = roles.map(role =>
          page.roles.includes(role)
            ? '<td class="arch-access-yes">✓</td>'
            : '<td class="arch-access-no">-</td>'
        ).join('');

        return `<tr><td>${escapeHtml(page.name)}</td>${cells}</tr>`;
      });

    tbody.innerHTML = rows.join('');
  }

  // ============================================
  // SEARCH
  // ============================================

  function bindSearch() {
    const searchDatabase = document.getElementById('searchDatabase');
    const searchPages = document.getElementById('searchPages');

    if (searchDatabase) {
      searchDatabase.addEventListener('input', (e) => {
        renderDatabaseTables(e.target.value);
      });
    }

    if (searchPages) {
      searchPages.addEventListener('input', (e) => {
        renderPages(e.target.value);
      });
    }
  }

  // ============================================
  // EXPORT
  // ============================================

  window.dmportal = window.dmportal || {};
  window.dmportal.initArchitectureMap = initArchitectureMap;
})();
