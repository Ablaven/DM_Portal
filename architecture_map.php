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
  <title>Architecture Map</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/architecture_map.css?v=20260306a" />
</head>
<body>
  <?php render_portal_navbar('architecture_map.php'); ?>

  <!-- Architecture toolbar (below navbar) -->
  <div class="arch-topbar">
    <h2><span>Architecture</span> Explorer</h2>
    <div class="sep"></div>
    <div class="arch-view-tabs">
      <button class="arch-view-tab active" data-view="er">ER Diagram</button>
      <button class="arch-view-tab" data-view="pages">Page Dependencies</button>
      <button class="arch-view-tab" data-view="roles">Role Access</button>
    </div>
    <div class="sep"></div>
    <div class="arch-search-wrap">
      <span class="arch-search-icon">&#x1F50E;</span>
      <input id="archSearchInput" type="text" placeholder="Search tables, pages, endpoints...">
    </div>
  </div>

  <!-- Legend -->
  <div class="arch-legend" id="archLegend"></div>

  <!-- Tooltip -->
  <div class="arch-tooltip" id="archTooltip"></div>

  <!-- Role access panel -->
  <div class="arch-info-panel" id="archInfoPanel"></div>

  <!-- Stats -->
  <div class="arch-stats-bar" id="archStatsBar"></div>

  <!-- Minimap -->
  <div class="arch-minimap" id="archMinimapWrap"><canvas id="minimapCanvas"></canvas></div>

  <!-- Main canvas -->
  <canvas id="graphCanvas"></canvas>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/navbar.js?v=20260228g"></script>
  <script src="js/architecture_map.js?v=20260306a"></script>
  <script>
    window.dmportal?.initNavbar?.({});
  </script>
</body>
</html>
