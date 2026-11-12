<?php
/**
 * Admin Authentication Guard
 * Include at the top of every admin page (after config/db.php).
 *  - Visitors who are not logged in are sent to the login page.
 *  - Admins still using the default password must change it before anything else.
 *  - Idle admins are signed out after ADMIN_IDLE_MINUTES (see start_session()).
 */

require_once __DIR__ . '/../config/db.php';

start_session();

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Robots-Tag: noindex, nofollow');

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['admin_must_change']) && basename($_SERVER['PHP_SELF']) !== 'password.php') {
    header('Location: password.php');
    exit;
}
