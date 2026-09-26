<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_login();
auth_require_roles(['admin']);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>System Architecture</title>
  <link rel="stylesheet" href="css/style.css?v=20260926a" />
  <link rel="stylesheet" href="css/architecture_map.css?v=20260926a" />
</head>
<body class="architecture-view">
  <?php render_portal_navbar('architecture_map.php'); ?>

  <main class="container container-top">
    <!-- Professional Header -->
    <div class="lectures-header">
      <div class="lectures-header-content">
        <h1 class="lectures-title">System Architecture</h1>
        <p class="lectures-subtitle">Database schema, page structure, and role permissions</p>
      </div>
    </div>

    <!-- Summary Stats -->
    <section class="arch-stats-section">
      <div class="arch-stat-card">
        <div class="arch-stat-icon" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <ellipse cx="12" cy="5" rx="9" ry="3"/>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
          </svg>
        </div>
        <div class="arch-stat-content">
          <div class="arch-stat-label">Database Tables</div>
          <div class="arch-stat-value" id="statsTableCount">—</div>
        </div>
      </div>
      
      <div class="arch-stat-card">
        <div class="arch-stat-icon" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
            <path d="M13 2v7h7"/>
          </svg>
        </div>
        <div class="arch-stat-content">
          <div class="arch-stat-label">PHP Pages</div>
          <div class="arch-stat-value" id="statsPageCount">—</div>
        </div>
      </div>
      
      <div class="arch-stat-card">
        <div class="arch-stat-icon" style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v6l4 2"/>
          </svg>
        </div>
        <div class="arch-stat-content">
          <div class="arch-stat-label">API Endpoints</div>
          <div class="arch-stat-value" id="statsEndpointCount">—</div>
        </div>
      </div>
      
      <div class="arch-stat-card">
        <div class="arch-stat-icon" style="background:linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
          </svg>
        </div>
        <div class="arch-stat-content">
          <div class="arch-stat-label">User Roles</div>
          <div class="arch-stat-value">4</div>
        </div>
      </div>
    </section>

    <!-- Tabs -->
    <div class="arch-tabs">
      <button class="arch-tab active" data-tab="database">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <ellipse cx="12" cy="5" rx="9" ry="3"/>
          <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
          <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
        </svg>
        Database Schema
      </button>
      <button class="arch-tab" data-tab="pages">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
          <path d="M13 2v7h7"/>
        </svg>
        Pages & Endpoints
      </button>
      <button class="arch-tab" data-tab="roles">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="8.5" cy="7" r="4"/>
          <path d="M20 8v6M23 11h-6"/>
        </svg>
        Role Permissions
      </button>
    </div>

    <!-- Database Schema Tab -->
    <section class="arch-content active" id="tab-database">
      <div class="arch-section-header">
        <h2>Database Tables</h2>
        <input type="text" id="searchDatabase" class="arch-search-input" placeholder="Search tables or columns...">
      </div>
      <div id="databaseTablesList" class="arch-grid"></div>
    </section>

    <!-- Pages & Endpoints Tab -->
    <section class="arch-content" id="tab-pages">
      <div class="arch-section-header">
        <h2>PHP Pages & API Endpoints</h2>
        <input type="text" id="searchPages" class="arch-search-input" placeholder="Search pages or endpoints...">
      </div>
      <div id="pagesList" class="arch-grid"></div>
    </section>

    <!-- Role Permissions Tab -->
    <section class="arch-content" id="tab-roles">
      <div class="arch-section-header">
        <h2>Role-Based Access Control</h2>
        <p class="arch-section-desc">View which user roles have access to each page</p>
      </div>
      <div class="arch-table-wrapper">
        <table class="arch-table" id="rolesTable">
          <thead>
            <tr>
              <th style="text-align:left;">Page</th>
              <th>Admin</th>
              <th>Management</th>
              <th>Teacher</th>
              <th>Student</th>
            </tr>
          </thead>
          <tbody id="rolesTableBody">
            <tr><td colspan="5" style="text-align:center;">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260926a"></script>
  <script src="js/navbar.js?v=20260926a"></script>
  <script src="js/architecture_map.js?v=20260926a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
    window.dmportal?.initArchitectureMap?.();
  </script>
</body>
</html>
