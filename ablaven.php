<?php
// Easter egg credits page. Access is granted only after completing the dashboard click combo.
require_once __DIR__ . '/php/_easter_egg_gate.php';
require_once __DIR__ . '/php/require_easter_egg.php';
$ok = true;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Credits</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/ablaven.css?v=20260228d" />
</head>
<body class="ablaven-egg">
  <canvas id="eggParticles" aria-hidden="true"></canvas>

  <?php require_once __DIR__ . '/php/_navbar.php'; render_portal_brand_header('index.php'); ?>

  <main class="container egg-wrap container-egg">
    <div class="egg-enter">
      <?php if (!$ok): ?>
        <section class="card egg-card">
          <div class="egg-hero">
            <h1 class="egg-title">Nothing to see here</h1>
            <p class="egg-sub">
              This page is an easter egg. It only appears after a special click sequence on the dashboard.
            </p>
            <div class="egg-actions">
              <a class="btn btn-secondary" href="index.php">Go to Dashboard</a>
            </div>
          </div>
        </section>
      <?php else: ?>
        <div id="eggTiltWrap" style="display:block;">
        <section class="card egg-card" id="eggCard">
          <div class="egg-hero">
            <div style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap; justify-content:space-between;">
              <div>
                <div class="egg-by">Made by</div>
                <h1 class="egg-title" data-text="Ablaven" style="margin-top:6px;">Ablaven</h1>
                <div class="egg-realname">Mazin Mohamed Diab</div>
                <div class="egg-joke">This web app only costs <strong>700 L.E</strong></div>
              </div>
              <span class="egg-badge">Easter Egg Unlocked</span>
            </div>

            <div class="egg-divider" aria-hidden="true"></div>

            <p class="egg-sub">
              Thanks for checking it out.
            </p>

            <div class="egg-actions">
              <a class="btn" href="index.php">Back to Dashboard</a>
            </div>
          </div>
        </section>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="js/core.js?v=20260228g"></script>
  <script src="js/ablaven.js?v=20260228d"></script>
</body>
</html>
