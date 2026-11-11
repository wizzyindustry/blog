<?php
/**
 * User Logout (POST with CSRF token, so other sites cannot log users out)
 */
require_once __DIR__ . '/../config/db.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    log_out_user();
    flash('success', 'You have been logged out.');
}
header('Location: login.php');
exit;
