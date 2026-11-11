<?php
/**
 * User Authentication Guard
 * Include at the top of every page in /account that needs a logged-in user.
 * Sets $user (the current user's row) and records the user as active.
 */
require_once __DIR__ . '/../config/db.php';

start_session();
$user = current_user();

if (!$user) {
    // Come back to this page after logging in (same-folder pages only)
    $next = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

touch_user_activity();
