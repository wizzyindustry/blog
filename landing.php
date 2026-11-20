<?php
/**
 * Old address of the welcome page. It is now index.php (the site root), so every host opens it
 * first. This file only keeps old bookmarks and links working (301 = moved permanently).
 */
require_once __DIR__ . '/includes/security.php';

header('Location: ' . app_base_path() . '/', true, 301);
exit;
