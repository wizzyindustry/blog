<?php
/**
 * Error screen used by Apache (ErrorDocument in .htaccess) for 400, 401, 403, 404, 405, 408,
 * 413, 500, 502, 503 and 504, e.g. a missing page, a blocked folder, or the admin lock.
 * PHP pages show the same screen through render_error_page().
 * Loads only the security layer (no database), so it works even when MySQL is down.
 */
require_once __DIR__ . '/includes/security.php';

$code = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);
if ($code < 400) {
    $code = (int) ($_GET['code'] ?? 404);
}
$allowed = [400, 401, 403, 404, 405, 408, 413, 429, 500, 502, 503, 504];
render_error_page(in_array($code, $allowed, true) ? $code : 404);
