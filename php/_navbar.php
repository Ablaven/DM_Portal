<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

function render_portal_brand_header(string $href = 'index.php'): void
{
    echo '<header class="navbar">';
    echo '<a class="brand" href="' . htmlspecialchars($href, ENT_QUOTES) . '">Digital Marketing Portal</a>';
    echo '<div class="nav-actions" style="margin-left:auto;">';
    echo '<button id="themeToggle" class="btn btn-secondary btn-small" type="button" aria-label="Toggle theme">Theme</button>';
    echo '</div>';
    echo '</header>';
}

function render_portal_navbar(string $activePage): void
{
    $u    = auth_current_user();
    $role = (string)($u['role'] ?? '');

    // Admin pages that trigger the indicator dot on the Admin dropdown button.
    $adminPages = [
        'admin_courses.php',
        'admin_doctors.php',
        'admin_students.php',
        'admin_users.php',
        'admin_panel.php',
        'hours_report.php',
        'hours_report_detail.php',
        'evaluation_reports.php',
        'attendance_report.php',
        'student_dashboard.php',
    ];
    $adminDropdownActive = in_array($activePage, $adminPages, true);

    echo '<header class="navbar">';
    echo '<a class="brand" href="' . htmlspecialchars(auth_nav_home_href(), ENT_QUOTES) . '">Digital Marketing Portal</a>';
    echo '<nav class="navlinks" aria-label="Main navigation">';

    // ── Primary link row ────────────────────────────────────────────────────
    echo '<div class="nav-row nav-row-primary">';

    auth_render_nav_link('index.php', 'Dashboard', $activePage);
    auth_render_nav_link('schedule_builder.php', 'Schedule Builder', $activePage);
    auth_render_nav_link('availability.php', 'Availability', $activePage);
    auth_render_nav_link('attendance.php', 'Attendance', $activePage);

    // Student Schedule — label depends on role.
    if ($role === 'student') {
        auth_render_nav_link('students.php', 'My Schedule', $activePage);
    } else {
        auth_render_nav_link('students.php', 'Student Schedule', $activePage);
    }

    auth_render_nav_link('evaluation.php', 'Evaluation', $activePage);

    // My Schedule (doctor.php) — teachers only.
    if ($role === 'teacher' && auth_can_access_page('doctor.php')) {
        auth_render_nav_link('doctor.php', 'My Schedule', $activePage);
    }

    // My Dashboard (student_dashboard.php) — students with a student_id.
    $studentId = (int)($u['student_id'] ?? 0);
    if ($role !== 'admin' && $role !== 'management' && $studentId > 0) {
        auth_render_nav_link('student_dashboard.php', 'My Dashboard', $activePage);
    }

    // My Lectures — external link, teachers/doctors only.
    $doctorId = (int)($u['doctor_id'] ?? 0);
    if ($role === 'teacher' || $doctorId > 0) {
        $lecturesUrl = 'https://sherifrostom9-boop.github.io/DM-Lectures/';
        echo '<a class="navlink" href="' . htmlspecialchars($lecturesUrl, ENT_QUOTES) . '" target="_blank" rel="noopener">My Lectures</a>';
    }

    auth_render_nav_link('profile.php', 'Profile', $activePage);

    // ── Admin dropdown (admin / management only) ────────────────────────────
    if ($role === 'admin' || $role === 'management') {
        $btnClass = 'navlink navlink-button' . ($adminDropdownActive ? ' active' : '');
        $dotHtml  = $adminDropdownActive
            ? '<span class="admin-indicator-dot" aria-hidden="true"></span>'
            : '';

        echo '<div class="nav-dropdown" id="adminNav">';
        echo '<button class="' . $btnClass . '" type="button" aria-haspopup="true" aria-expanded="false">';
        echo 'Admin' . $dotHtml;
        echo '</button>';
        echo '<div class="dropdown" role="menu" aria-label="Admin menu">';

        // — Section: Manage ——————————————————————————————————————————
        // Helper: render a rich dropdown item with icon + label + optional sub-text
        $ddItem = function(string $href, string $icon, string $label, string $sub, bool $isActive) {
            $activeClass = $isActive ? ' active' : '';
            $ariaCurrent = $isActive ? ' aria-current="page"' : '';
            echo '<a class="dropdown-item dropdown-item-rich' . $activeClass . '"' . $ariaCurrent . ' href="' . htmlspecialchars($href, ENT_QUOTES) . '">';
            echo '<span class="ddi-icon" aria-hidden="true">' . $icon . '</span>';
            echo '<span class="ddi-body"><span class="ddi-label">' . htmlspecialchars($label) . '</span>';
            if ($sub !== '') echo '<span class="ddi-sub">' . htmlspecialchars($sub) . '</span>';
            echo '</span></a>';
        };

        // ─ Section: Manage ───────────────────────────────────────────────────
        echo '<div class="dropdown-section-label">Manage</div>';

        if (auth_can_access_page('admin_courses.php')) {
            $ddItem('admin_courses.php', '📚', 'Courses', 'Add, edit & assign courses', $activePage === 'admin_courses.php');
        }
        if (auth_can_access_page('admin_doctors.php')) {
            $ddItem('admin_doctors.php', '🧑‍🏫', 'Doctors', 'Manage teaching staff', $activePage === 'admin_doctors.php');
        }
        if (auth_can_access_page('admin_students.php')) {
            $ddItem('admin_students.php', '🎓', 'Students', 'View & manage students', $activePage === 'admin_students.php');
        }
        if (auth_can_access_page('admin_users.php')) {
            $ddItem('admin_users.php', '🔑', 'User Accounts', 'Logins & permissions', $activePage === 'admin_users.php');
        }

        // ─ Section: Tools (admin only) ───────────────────────────────────────
        if ($role === 'admin' && auth_can_access_page('admin_panel.php')) {
            echo '<div class="dropdown-section-label">Tools</div>';
            $ddItem('admin_panel.php', '⚙️', 'Admin Panel', 'Semesters, terms & settings', $activePage === 'admin_panel.php');
        }

        // ─ Section: Reports ──────────────────────────────────────────────────
        echo '<div class="dropdown-section-label">Reports</div>';

        if (auth_can_access_page('hours_report.php')) {
            $hubLabel = ($role === 'teacher') ? 'My Reports' : 'Reports Hub';
            $ddItem('hours_report.php', '🗂️', $hubLabel, 'Overview of all report modules', $activePage === 'hours_report.php');
        }
        if (auth_can_access_page('hours_report_detail.php')) {
            $hoursLabel = ($role === 'teacher') ? 'My Hours' : 'Hours Report';
            $ddItem('hours_report_detail.php', '⏱️', $hoursLabel, 'Doctor hours & course breakdown', $activePage === 'hours_report_detail.php');
        }
        if (auth_can_access_page('evaluation_reports.php')) {
            $ddItem('evaluation_reports.php', '📝', 'Evaluation Reports', 'Grades & assessment summaries', $activePage === 'evaluation_reports.php');
        }
        if (auth_can_access_page('attendance_report.php')) {
            $ddItem('attendance_report.php', '✅', 'Attendance Report', 'Participation rates & history', $activePage === 'attendance_report.php');
        }
        if (auth_can_access_page('student_dashboard.php')) {
            $ddItem('student_dashboard.php', '📈', 'Student Dashboard', 'Per-student performance view', $activePage === 'student_dashboard.php');
        }

        echo '</div>'; // .dropdown
        echo '</div>'; // .nav-dropdown#adminNav
    }

    // ── Doctors dropdown (admin / management, not teacher) ──────────────────
    if (auth_can_access_page('doctor.php') && $role !== 'teacher') {
        echo '<div class="nav-dropdown" id="doctorsNav">';
        echo '<button class="navlink navlink-button" type="button" aria-haspopup="true" aria-expanded="false">Doctors</button>';
        echo '<div class="dropdown" role="menu" aria-label="Doctors list">';
        echo '<div class="dropdown-item muted">Loading…</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>'; // .nav-row.nav-row-primary

    // ── Right-side actions ──────────────────────────────────────────────────
    echo '<div class="nav-actions">';
    echo '<button id="themeToggle" class="btn btn-secondary btn-small" type="button" aria-label="Toggle theme">Theme</button>';
    echo '<button id="logoutBtn" class="btn btn-secondary btn-small nav-logout" type="button">Logout</button>';
    echo '</div>';

    echo '</nav>';
    echo '</header>';
}
