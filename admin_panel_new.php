<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';
require_once __DIR__ . '/php/_system_settings_helpers.php';
require_once __DIR__ . '/php/db_connect.php';

auth_require_page_access('admin_panel.php');
auth_require_roles(['admin']);

// Get current attendance timeout status
$pdo = get_pdo();
$timeoutStatus = dmportal_get_attendance_timeout_status($pdo);

$importStatus = $_GET['import_status'] ?? '';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Panel</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
</head>
<body>
  <?php render_portal_navbar('admin_panel.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Admin Panel</h1>
      <p class="subtitle">Central place for admin-only tools and utilities.</p>
    </header>

    <!-- ── Tab Navigation ──────────────────────────────────────────────────── -->
    <div class="tabs" style="margin-bottom:24px;">
      <button class="tab active" data-tab="reports" type="button">📊 Reports & Tracking</button>
      <button class="tab" data-tab="settings" type="button">⚙️ System Settings</button>
      <button class="tab" data-tab="academic" type="button">🗓️ Academic Year</button>
      <button class="tab" data-tab="database" type="button">💾 Database</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         TAB 1: REPORTS & TRACKING
         ════════════════════════════════════════════════════════════════════════ -->
    <div id="tab-reports" class="tab-panel">
