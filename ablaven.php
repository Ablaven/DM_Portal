<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
// Easter egg credits page. Access is granted only after completing the dashboard click combo.
require_once __DIR__ . '/php/_easter_egg_gate.php';
require_once __DIR__ . '/php/require_easter_egg.php';
// If we reach here, access is granted (require_easter_egg.php would have exited otherwise)
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Credits</title>
  <link rel="stylesheet" href="css/style.css?v=20260222d" />
  <link rel="stylesheet" href="css/ablaven.css?v=20260512a" />
</head>
<body class="ablaven-egg">
  <canvas id="eggParticles" aria-hidden="true"></canvas>
  
  <!-- Achievement Toast Container -->
  <div id="achievementContainer" class="achievement-container"></div>
  
  <!-- Sound Toggle Button -->
  <button id="soundToggle" class="sound-toggle-btn" aria-label="Toggle sound">🔊 Sound ON</button>

  <?php require_once __DIR__ . '/php/_navbar.php'; render_portal_brand_header('index.php'); ?>

  <main class="container egg-wrap container-egg">
    <div class="egg-enter">
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
              Thanks for checking it out. <strong>Click anywhere for fireworks!</strong><br>
              <em style="opacity:0.6;">Hint: Try the Konami code... ↑↑↓↓←→←→BA</em>
            </p>

            <div class="egg-actions">
              <a class="btn" href="index.php">Back to Dashboard</a>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>

  <script src="js/core.js?v=20260228h"></script>
  <script src="js/ablaven.js?v=20260512a"></script>
</body>
</html>