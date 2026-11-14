<?php
/**
 * Google sends the browser back here after sign-in (the "Authorized redirect URI").
 * Checks state (one use only), exchanges the code with the PKCE verifier, verifies the ID token,
 * then signs the student in, connects Google, or asks them to finish creating an account.
 */
require_once __DIR__ . '/../config/db.php';

if (!google_enabled()) {
    render_error_page(404);
}
start_session();

header('Referrer-Policy: no-referrer'); // The code and state in this URL never leak to other sites
header('Cache-Control: no-store');

// Too many forged / replayed callbacks from this network: stop answering them
$ip = client_ip();
if (rate_limited('googlecb', $ip, GOOGLE_STATE_FAIL_LIMIT, GOOGLE_STATE_FAIL_WINDOW)) {
    too_many_requests('Too many failed Google sign-in attempts from your network. Please wait '
        . GOOGLE_STATE_FAIL_WINDOW . ' minutes and try again.');
}

// The stored flow can be used exactly once, whatever happens next
$flow = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);
$is_link = is_array($flow) && ($flow['mode'] ?? '') === 'link';

// 1. STRICT state check FIRST - for every reply, including "cancelled" and other errors
$problem = google_check_callback_state($flow, $_GET, (string) ($_SERVER['QUERY_STRING'] ?? ''), (string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if ($problem !== null) {
    rate_hit('googlecb', $ip);
    $ref = strtoupper(bin2hex(random_bytes(4)));
    // Logged for security monitoring (never the state, code or token values)
    error_log('[ref ' . $ref . '] Google sign-in callback REJECTED (' . $problem . ') from ' . $ip
        . (isset($_SESSION['user_id']) ? ' while user #' . (int) $_SESSION['user_id'] . ' was signed in' : ''));
    render_error_page(400, 'Google sign-in could not be completed',
        'This sign-in link is invalid, was already used, or has expired. Please go back and choose "Continue with Google" again.', $ref);
}

// 2. The student pressed "Cancel" on Google's page (or Google refused): no error screen needed
$google_error = input_text($_GET, 'error');
if ($google_error !== '') {
    flash('error', $google_error === 'access_denied'
        ? 'Google sign-in was cancelled. You can try again or sign in with your username and password.'
        : 'Google sign-in did not complete. Please try again.');
    header('Location: ' . ($is_link ? 'security.php' : 'login.php'), true, 303);
    exit;
}

$code = input_text($_GET, 'code');
if ($code === '' || strlen($code) > 1024) {
    render_error_page(400, 'Google sign-in could not be completed', 'Google did not send a sign-in code. Please try again.');
}

$ref = strtoupper(bin2hex(random_bytes(4)));
$id_token = google_exchange_code($code, (string) $flow['verifier']);
if ($id_token === null) {
    error_log('[ref ' . $ref . '] Google sign-in: token exchange failed');
    render_error_page(502, 'Google sign-in is unavailable', 'We could not reach Google to confirm your sign-in. Please try again in a moment.', $ref);
}

$claims = google_verify_id_token($id_token, (string) $flow['nonce']);
unset($id_token);
if ($claims === null) {
    error_log('[ref ' . $ref . '] Google sign-in: ID token verification failed');
    render_error_page(400, 'Google sign-in could not be verified', 'We could not confirm your Google sign-in. Please try again.', $ref);
}

try {
    $next = google_handle_identity($pdo, $claims, $flow);
} catch (PDOException $e) {
    error_log('[ref ' . $ref . '] Google sign-in: ' . $e->getMessage());
    render_error_page(500, null, null, $ref);
}
header('Location: ' . $next, true, 303);
exit;
