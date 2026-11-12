<?php
/**
 * Admin Logout - POST with CSRF token only, so another website cannot sign the admin out.
 * Only the admin part of the session is cleared (a student session in the same browser is kept).
 */
require_once __DIR__ . '/../config/db.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    header('Location: ' . (!empty($_SESSION['admin_logged_in']) ? 'dashboard.php' : 'login.php'));
    exit;
}

unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_must_change']);
session_regenerate_id(true);
unset($_SESSION['csrf_token']);

header('Location: login.php');
exit;
