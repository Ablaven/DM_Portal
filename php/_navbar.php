<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

function render_portal_brand_header(string $href = 'index.php'): void
{
    echo '<header class="navbar">';
    echo '<a class="brand" href="' . htmlspecialchars($href, ENT_QUOTES) . '">Digital Marketing Portal</a>';
    echo '</header>';
}

function render_portal_navbar(string $activePage): void
{
    $u = auth_current_user();
    echo '<header class="navbar">';
    echo '<a class="brand" href="' . htmlspecialchars(auth_nav_home_href(), ENT_QUOTES) . '">Digital Marketing Portal</a>';
    echo '<nav class="navlinks" aria-label="Main navigation">';
    echo '<div class="nav-actions">';
    echo '<button id="themeToggle" class="btn btn-secondary btn-small" type="button" aria-label="Toggle theme">Theme</button>';
    echo '<button id="logoutBtn" class="btn btn-secondary btn-small nav-logout" type="button">Logout</button>';
    echo '</div>';

    echo '<div class="nav-row nav-row-primary">';
    auth_render_nav_link('index.php', 'Dashboard', $activePage);
    auth_render_nav_link('schedule_builder.php', 'Doctor Schedule Builder', $activePage);
    auth_render_nav_link('availability.php', 'Availability', $activePage);
    if (($u['role'] ?? '') === 'student') {
        auth_render_nav_link('students.php', 'My Schedule', $activePage);
    } else {
        auth_render_nav_link('students.php', 'Student Schedule', $activePage);
    }
    auth_render_nav_link('attendance.php', 'Attendance', $activePage);
    auth_render_nav_link('evaluation.php', 'Evaluation', $activePage);
    $studentId = (int)($u['student_id'] ?? 0);
    if (($u['role'] ?? '') === 'admin' || ($u['role'] ?? '') === 'management') {
        auth_render_nav_link('student_dashboard.php', 'Student Dashboard', $activePage);
    } elseif ($studentId > 0) {
        auth_render_nav_link('student_dashboard.php', 'My Dashboard', $activePage);
    }
    if (($u['role'] ?? '') === 'teacher' && auth_can_access_page('doctor.php')) {
        auth_render_nav_link('doctor.php', 'My Schedule', $activePage);
    }

    $role = (string)($u['role'] ?? '');
    $doctorId = (int)($u['doctor_id'] ?? 0);
    if ($role === 'teacher' || $doctorId > 0) {
        $lecturesUrl = 'https://sherifrostom9-boop.github.io/Digital-Marketing-tutting-plan/';
        echo '<a class="navlink" href="' . htmlspecialchars($lecturesUrl, ENT_QUOTES) . '" target="_blank" rel="noopener">My Lectures</a>';
    }
    echo '</div>';

    echo '<div class="nav-row nav-row-secondary">';
    auth_render_nav_link('admin_courses.php', 'Course Management', $activePage);
    auth_render_nav_link('admin_doctors.php', 'Doctor Management', $activePage);
    auth_render_nav_link('admin_students.php', 'Student Management', $activePage);
    auth_render_nav_link('admin_users.php', 'User Accounts', $activePage);

    if (($u['role'] ?? '') === 'admin') {
        auth_render_nav_link('hours_report.php', 'Hours Report', $activePage);
    }

    if (auth_can_access_page('doctor.php') && (($u['role'] ?? '') !== 'teacher')) {
        echo '<div class="nav-dropdown" id="doctorsNav">'
            . '<button class="navlink navlink-button" type="button" aria-haspopup="true" aria-expanded="false">Doctors</button>'
            . '<div class="dropdown" role="menu" aria-label="Doctors list">'
            . '<div class="dropdown-item muted">Loading…</div>'
            . '</div>'
            . '</div>';
    }

    auth_render_nav_link('profile.php', 'Profile', $activePage);
    echo '</div>';

    echo '</nav>';
    echo '</header>';
}
