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
    { name: 'portal_users', module: 'Authentication', columns: ['user_id', 'username', 'password_hash', 'role', 'doctor_id', 'student_id', 'allowed_pages', 'is_active'], fks: ['doctor_id → doctors', 'student_id → students'] },
    { name: 'doctors', module: 'Staff', columns: ['doctor_id', 'full_name', 'email', 'doctor_type', 'phone', 'color_code'], fks: [] },
    { name: 'students', module: 'Academic', columns: ['student_id', 'student_code', 'full_name', 'email', 'year_level', 'semester', 'program'], fks: [] },
    { name: 'courses', module: 'Academic', columns: ['course_id', 'course_name', 'course_type', 'subject_code', 'year_level', 'semester', 'total_hours'], fks: [] },
    { name: 'terms', module: 'Academic', columns: ['term_id', 'academic_year_id', 'label', 'semester', 'is_active'], fks: ['academic_year_id → academic_years'] },
    { name: 'weeks', module: 'Scheduling', columns: ['week_id', 'term_id', 'label', 'start_date', 'end_date', 'status', 'week_type'], fks: ['term_id → terms'] },
    { name: 'doctor_schedules', module: 'Scheduling', columns: ['schedule_id', 'week_id', 'doctor_id', 'course_id', 'day_of_week', 'slot_number', 'room_code'], fks: ['week_id → weeks', 'doctor_id → doctors', 'course_id → courses'] },
    { name: 'availability', module: 'Scheduling', columns: ['availability_id', 'doctor_id', 'week_id', 'day_of_week', 'slot_number'], fks: ['doctor_id → doctors', 'week_id → weeks'] },
    { name: 'doctor_unavailability', module: 'Scheduling', columns: ['unavailability_id', 'doctor_id', 'start_datetime', 'end_datetime', 'reason'], fks: ['doctor_id → doctors'] },
    { name: 'attendance_records', module: 'Attendance', columns: ['attendance_id', 'schedule_id', 'student_id', 'status', 'recorded_at'], fks: ['schedule_id → doctor_schedules', 'student_id → students'] },
    { name: 'evaluation_configs', module: 'Evaluation', columns: ['config_id', 'course_id', 'year_level', 'semester', 'academic_year_id'], fks: ['course_id → courses'] },
    { name: 'evaluation_grades', module: 'Evaluation', columns: ['grade_id', 'config_id', 'student_id', 'final_score', 'graded_at'], fks: ['config_id → evaluation_configs', 'student_id → students'] },
    { name: 'rooms', module: 'Facilities', columns: ['room_id', 'room_code', 'room_name', 'floor_id', 'capacity'], fks: ['floor_id → floors'] },
    { name: 'floors', module: 'Facilities', columns: ['floor_id', 'floor_name', 'floor_number'], fks: [] },
    { name: 'course_doctor_hours', module: 'Scheduling', columns: ['id', 'course_id', 'doctor_id', 'allocated_hours'], fks: ['course_id → courses', 'doctor_id → doctors'] },
  ];

  const PAGES = [
    { name: 'dashboard.php', type: 'page', desc: 'Main dashboard for teachers', roles: ['teacher'] },
    { name: 'student_dashboard.php', type: 'page', desc: 'Student dashboard and schedule', roles: ['student'] },
    { name: 'admin_panel.php', type: 'page', desc: 'Admin control panel', roles: ['admin', 'management'] },
    { name: 'admin_users.php', type: 'page', desc: 'User account management', roles: ['admin'] },
    { name: 'admin_doctors.php', type: 'page', desc: 'Doctor management', roles: ['admin', 'management'] },
    { name: 'admin_students.php', type: 'page', desc: 'Student management', roles: ['admin', 'management'] },
    { name: 'admin_courses.php', type: 'page', desc: 'Course management', roles: ['admin', 'management'] },
    { name: 'schedule_builder.php', type: 'page', desc: 'Build doctor schedules', roles: ['admin', 'management'] },
    { name: 'availability.php', type: 'page', desc: 'Manage doctor availability', roles: ['admin', 'management', 'teacher'] },
    { name: 'doctor.php', type: 'page', desc: 'View doctor schedule', roles: ['admin', 'management', 'teacher'] },
    { name: 'students.php', type: 'page', desc: 'View student schedule', roles: ['admin', 'management', 'student'] },
    { name: 'attendance.php', type: 'page', desc: 'Mark attendance', roles: ['teacher'] },
    { name: 'attendance_report.php', type: 'page', desc: 'Attendance reports', roles: ['admin', 'management'] },
    { name: 'evaluation.php', type: 'page', desc: 'Grade entry', roles: ['teacher'] },
    { name: 'evaluation_reports.php', type: 'page', desc: 'Evaluation reports', roles: ['admin', 'management'] },
    { name: 'hours_report.php', type: 'page', desc: 'Teaching hours report', roles: ['admin', 'management'] },
    { name: 'lectures.php', type: 'page', desc: 'Lecture file management', roles: ['teacher'] },
    { name: 'profile.php', type: 'page', desc: 'User profile', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'get_courses.php', type: 'api', desc: 'Fetch courses', roles: ['admin', 'management'] },
    { name: 'get_doctors.php', type: 'api', desc: 'Fetch doctors', roles: ['admin', 'management'] },
    { name: 'get_schedule.php', type: 'api', desc: 'Fetch schedule data', roles: ['admin', 'management', 'teacher', 'student'] },
    { name: 'save_schedule.php', type: 'api', desc: 'Save schedule changes', roles: ['admin', 'management'] },
    { name: 'mark_attendance.php', type: 'api', desc: 'Submit attendance', roles: ['teacher'] },
    { name: 'save_grades.php', type: 'api', desc: 'Save evaluation grades', roles: ['teacher'] },
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
