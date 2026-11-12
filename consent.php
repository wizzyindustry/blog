<?php
/**
 * Saves the cookie choice when JavaScript is off (the banner buttons link here).
 * With JavaScript the choice is saved in the browser without a page load.
 */
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/seo.php';

$choice = ($_GET['choice'] ?? '') === 'all' ? 'all' : 'essential';
setcookie(CONSENT_COOKIE, $choice, [
    'expires' => time() + 365 * 86400,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => false, // The banner script reads it to decide whether to show itself
    'samesite' => 'Lax',
]);

// Go back to the page the visitor was on (same site only)
$return = (string) ($_GET['return'] ?? '');
if ($return === '' || $return[0] !== '/' || preg_match('#^/[/\\\\]#', $return) || preg_match('/[\r\n]/', $return)) {
    $return = app_base_path() . '/landing.php';
}
header('Location: ' . $return, true, 303);
exit;
