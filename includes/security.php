<?php
/**
 * Security Layer - loaded FIRST by config/db.php on every request.
 * UDOM Student Materials Blog
 *
 *  1. Secrets          read from config/secrets.php or environment variables (never from code / the browser)
 *  2. Debug mode OFF   errors are logged to logs/php-errors.log, never shown to visitors
 *  3. HTTPS            every HTTP request is redirected to HTTPS; HSTS tells browsers to stay on HTTPS
 *  4. Security headers Content-Security-Policy (blocks injected scripts), clickjacking, MIME sniffing...
 *  5. Rate limiting    generic per-IP / per-user limits for logins, sign-ups, comments, uploads, search
 *  6. HTML sanitizer   allow-list cleaner for post content written by the admin
 */

/* --------------------------------------------------------------
 * 1. Secrets
 * ------------------------------------------------------------ */

/** A configuration value: environment variable first, then config/secrets.php, then $default. */
function app_config(string $key, $default = null)
{
    static $file = null;
    if ($file === null) {
        $path = __DIR__ . '/../config/secrets.php';
        $file = is_file($path) ? (array) require $path : [];
    }
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $file[$key] ?? $default;
}

/* --------------------------------------------------------------
 * 2. Debug mode off: log errors, never display them
 * ------------------------------------------------------------ */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('html_errors', '0');
error_reporting(E_ALL);

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0750, true);
}
if (is_dir($log_dir) && !is_file($log_dir . '/.htaccess')) {
    @file_put_contents($log_dir . '/.htaccess', "Require all denied\n");
}
ini_set('error_log', $log_dir . '/php-errors.log');

// Branded error screens (no database needed)
require_once __DIR__ . '/error_page.php';

// Any uncaught error: log the details with a reference, show the visitor a friendly error screen
set_exception_handler(function (Throwable $e) {
    $ref = strtoupper(bin2hex(random_bytes(4)));
    error_log('[ref ' . $ref . '] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error ' . $ref . ': ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    render_error_page(500, null, null, $ref);
});

// Fatal errors (out of memory, timeouts) also get the error screen instead of a blank page
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && PHP_SAPI !== 'cli') {
        $ref = strtoupper(bin2hex(random_bytes(4)));
        error_log('[ref ' . $ref . '] Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        render_error_page(strpos($err['message'], 'Maximum execution time') !== false ? 504 : 500, null, null, $ref);
    }
});

/* --------------------------------------------------------------
 * 3. HTTPS everywhere
 * ------------------------------------------------------------ */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** True when config says HTTPS must be used (live site). */
function https_required(): bool
{
    return filter_var(app_config('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);
}

/**
 * Pick the protocol (also done in .htaccess; this covers servers without mod_rewrite):
 *   FORCE_HTTPS = true -> plain HTTP is redirected to HTTPS (live site)
 *   FORCE_HTTP  = true -> HTTPS is redirected to plain HTTP (local development, current setting)
 */
function enforce_https(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    if (!is_https() && https_required()) {
        header('Location: https://' . preg_replace('/:80$/', '', $host) . $uri, true, 301);
        exit;
    }
    if (is_https() && !https_required() && filter_var(app_config('FORCE_HTTP', false), FILTER_VALIDATE_BOOLEAN)) {
        header('Strict-Transport-Security: max-age=0'); // Browser forgets any earlier "HTTPS only" rule
        header('Location: http://' . preg_replace('/:443$/', '', $host) . $uri, true, 302);
        exit;
    }
}

/* --------------------------------------------------------------
 * 4. Security headers
 * ------------------------------------------------------------ */

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header_remove('X-Powered-By'); // Do not advertise the PHP version

    // Only our own scripts plus the two CDNs we load (with Subresource Integrity). No inline scripts.
    $csp = [
        "default-src 'self'",
        "script-src 'self' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data:",
        "media-src 'self'",
        "frame-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];
    if (is_https() && https_required()) {
        $csp[] = 'upgrade-insecure-requests';
        // Browsers remember to use HTTPS for one year
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    } elseif (is_https()) {
        header('Strict-Transport-Security: max-age=0'); // HTTP mode: clear any earlier HSTS rule
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

enforce_https();
send_security_headers();

/* --------------------------------------------------------------
 * 5. Rate limiting
 *    Uses the login_attempts table: one row per counted action.
 *    scope = what is limited ('admin', 'user', 'register', 'comment', 'upload', 'password', 'search')
 *    ip_address = who (IP address, or "u:<user id>" for per-account limits)
 * ------------------------------------------------------------ */

/** True when $who already did $scope $max times in the last $minutes. */
function rate_limited(string $scope, string $who, int $max, int $minutes): bool
{
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE scope = :s AND ip_address = :w
             AND attempted_at > (NOW() - INTERVAL ' . (int) $minutes . ' MINUTE)'
        );
        $stmt->execute([':s' => $scope, ':w' => mb_substr($who, 0, 45)]);
        return (int) $stmt->fetchColumn() >= $max;
    } catch (PDOException $e) {
        error_log('rate_limited: ' . $e->getMessage());
        return false;
    }
}

/** Count one $scope action for $who. */
function rate_hit(string $scope, string $who): void
{
    global $pdo;
    try {
        $pdo->prepare('INSERT INTO login_attempts (scope, ip_address) VALUES (:s, :w)')
            ->execute([':s' => $scope, ':w' => mb_substr($who, 0, 45)]);
        // Occasionally prune rows older than a day so the table stays small
        if (mt_rand(1, 50) === 1) {
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        }
    } catch (PDOException $e) {
        error_log('rate_hit: ' . $e->getMessage());
    }
}

/** Stop with HTTP 429 "Too Many Requests". */
function too_many_requests(string $message, bool $json = false): void
{
    http_response_code(429);
    header('Retry-After: 600');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
    render_error_page(429, null, $message);
}

/* --------------------------------------------------------------
 * 6. HTML sanitizer (allow-list, DOM based)
 *    Only the listed tags and attributes survive; links and images may only
 *    use http(s), mailto or relative URLs. Everything else is removed.
 * ------------------------------------------------------------ */

const SAFE_HTML_TAGS = [
    'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
    'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [], 'hr' => [],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
];

function safe_url(string $url): bool
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $url = preg_replace('/[\x00-\x20]+/', '', $url); // "java\tscript:" tricks
    if ($url === '' || $url[0] === '#' || $url[0] === '/' || $url[0] === '?' || $url[0] === '.') {
        return true; // Relative link (external images are also blocked by the CSP img-src rule)
    }
    if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
        return true; // relative path like "post.php?id=1"
    }
    return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true);
}

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $doc->getElementById('__root');
    if (!$root) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }
    sanitize_node($root);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function sanitize_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->nodeName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'template', 'noscript'], true)) {
                $node->removeChild($child); // Dangerous: drop with its content
                continue;
            }
            if (!isset(SAFE_HTML_TAGS[$tag])) {
                // Unknown tag: keep its text/children, drop the tag itself
                sanitize_node($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                $keep = in_array($name, SAFE_HTML_TAGS[$tag], true);
                if ($keep && in_array($name, ['href', 'src'], true) && !safe_url($attr->nodeValue)) {
                    $keep = false;
                }
                if (!$keep) {
                    $child->removeAttribute($attr->nodeName);
                }
            }
            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            sanitize_node($child);
        } elseif (!($child instanceof DOMText)) {
            $node->removeChild($child); // Comments, processing instructions, CDATA
        }
    }
}
