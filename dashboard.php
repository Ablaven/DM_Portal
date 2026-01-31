<?php
// Backward-compatible redirect: the landing page is now index.php (Course Dashboard).
header('Location: index.php', true, 302);
exit;
