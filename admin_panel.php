<?php

declare(strict_types=1);

require_once __DIR__ . '/php/_auth.php';
require_once __DIR__ . '/php/_navbar.php';

auth_require_page_access('admin_panel.php');
auth_require_roles(['admin']);

$importStatus = $_GET['import_status'] ?? '';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Panel</title>
  <link rel="stylesheet" href="css/style.css?v=20251229" />
</head>
<body>
  <?php render_portal_navbar('admin_panel.php'); ?>

  <main class="container container-top">
    <header class="page-header">
      <h1>Admin Panel</h1>
      <p class="subtitle">Central place for admin-only tools and utilities.</p>
    </header>

    <section class="card">
      <div class="panel-title-row" style="margin-bottom:10px; align-items:flex-end;">
        <div>
          <h2 style="margin:0;">Database Tools</h2>
          <div class="muted" style="margin-top:4px;">Export or import a full SQL dump (admin only).</div>
        </div>
      </div>

      <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <a class="btn btn-secondary" href="php/export_database_sql.php">Export Database SQL</a>
        <form class="field" action="php/import_database_sql.php" method="post" enctype="multipart/form-data" style="margin:0;">
          <label class="muted" style="font-size:0.85rem;" for="importSqlFile">Import SQL</label>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input id="importSqlFile" name="sql_file" type="file" accept=".sql" required />
            <button class="btn btn-secondary" type="submit">Upload</button>
          </div>
        </form>
        <?php if ($importStatus === 'success') : ?>
          <div class="status success" role="status">Import completed successfully.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script src="js/core.js?v=20260121"></script>
  <script src="js/navbar.js?v=20260121"></script>
  <script>
    window.dmportal?.initNavbar?.({});
  </script>
</body>
</html>
