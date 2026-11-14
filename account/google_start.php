<?php
/**
 * Starts "Sign in with Google": stores state / nonce / PKCE in the session and redirects to Google.
 *   GET  ?return=/blog/account/files.php   sign in or sign up (logged-out visitors)
 *   POST mode=link + CSRF token            connect Google to the signed-in account
 */
require_once __DIR__ . '/../config/db.php';

if (!google_enabled()) {
    render_error_page(404);
}
start_session();

$ip = client_ip();
if (rate_limited('google', $ip, GOOGLE_START_LIMIT, GOOGLE_START_WINDOW)) {
    too_many_requests('Too many Google sign-in attempts from your network. Please wait ' . GOOGLE_START_WINDOW . ' minutes and try again.');
}
rate_hit('google', $ip);

$mode = 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        render_error_page(419);
    }
    if (input_text($_POST, 'mode') === 'link') {
        if (!current_user()) {
            header('Location: login.php?next=security.php', true, 303);
            exit;
        }
        $mode = 'link';
    }
}

$return = $mode === 'login' ? input_text($_GET, 'return') : '';
header('Location: ' . google_auth_url($mode, $return), true, 303);
exit;
