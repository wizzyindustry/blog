<?php
/**
 * Admin Authentication Guard
 * Include at the top of every admin page (after config/db.php).
 * Visitors who are not logged in are sent to the login page.
 */

require_once __DIR__ . '/../config/db.php';

start_session();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
