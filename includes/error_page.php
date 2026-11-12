<?php
/**
 * Branded error screens for every failure state.
 * Used by error.php (Apache ErrorDocument) and by PHP code (render_error_page()).
 * Works even when the database is down: it needs nothing but this file.
 */

/** URL path of the project folder, e.g. "/blog" (or "" when installed at the web root). */
function app_base_path(): string
{
    static $base = null;
    if ($base === null) {
        $root = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $project = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
        $base = ($root !== '' && stripos($project, $root) === 0) ? substr($project, strlen($root)) : '';
        $base = '/' . trim($base, '/');
        $base = $base === '/' ? '' : $base;
    }
    return $base;
}

/** Full URL of a page in this site, e.g. site_url('post.php?id=3'). APP_URL in secrets overrides the detected host. */
function site_url(string $path = ''): string
{
    $configured = function_exists('app_config') ? (string) app_config('APP_URL', '') : '';
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host . app_base_path() . '/' . ltrim($path, '/');
}

/** Title, explanation and icon for each status code. */
function error_page_info(int $code): array
{
    $pages = [
        400 => ['Bad request', 'Your browser sent something we could not understand. Please go back and try again.', 'fa-circle-exclamation'],
        401 => ['Please sign in', 'You need to sign in to see this page.', 'fa-user-lock'],
        403 => ['Access denied', 'You do not have permission to open this page. If you think this is a mistake, sign in with the right account or contact the administrator.', 'fa-lock'],
        404 => ['Page not found', 'The page you are looking for does not exist, was moved, or the link is broken. Try searching the study materials instead.', 'fa-map-location-dot'],
        405 => ['Action not allowed', 'This page cannot be used that way. Please go back and use the buttons on the page.', 'fa-ban'],
        408 => ['Request timed out', 'The page took too long to respond. Check your connection and try again.', 'fa-hourglass-end'],
        413 => ['File too large', 'What you tried to upload is bigger than the server allows. Please choose smaller files or upload fewer at a time.', 'fa-file-circle-exclamation'],
        419 => ['Session expired', 'Your page was open for too long and its security token expired. Please go back, refresh the page and try again.', 'fa-clock-rotate-left'],
        429 => ['Too many requests', 'You are doing that too often. Please wait a few minutes and try again.', 'fa-gauge-high'],
        500 => ['Something went wrong', 'An unexpected error happened on our side. It has been logged and we will look into it. Please try again in a moment.', 'fa-triangle-exclamation'],
        502 => ['Service unavailable', 'The server is having trouble reaching one of its services. Please try again in a few minutes.', 'fa-plug-circle-xmark'],
        503 => ['Temporarily unavailable', 'The site is down for maintenance or under heavy load. Please try again in a few minutes.', 'fa-screwdriver-wrench'],
        504 => ['Server timed out', 'The server took too long to respond. Please try again in a moment.', 'fa-hourglass-end'],
    ];
    return $pages[$code] ?? $pages[500];
}

/**
 * Send a full error screen and stop.
 * $reference: short ID shown to the visitor and written to the log, so a report can be matched.
 */
function render_error_page(int $code, ?string $title = null, ?string $message = null, ?string $reference = null): void
{
    [$default_title, $default_message, $icon] = error_page_info($code);
    $title = $title ?? $default_title;
    $message = $message ?? $default_message;
    $http_code = isset([400 => 1, 401 => 1, 403 => 1, 404 => 1, 405 => 1, 408 => 1, 413 => 1, 429 => 1, 500 => 1, 502 => 1, 503 => 1, 504 => 1][$code]) ? $code : ($code === 419 ? 400 : 500);
    $base = app_base_path();
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http_code);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        if ($code === 429 || $code === 503) {
            header('Retry-After: 300');
        }
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . $e($http_code . ' - ' . $title) . ' | UDOM Student Materials</title>'
        . '<meta name="description" content="' . $e($message) . '">'
        . '<link rel="icon" href="' . $base . '/favicon.ico" sizes="32x32"><link rel="icon" href="' . $base . '/assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">'
        . '<link rel="stylesheet" href="' . $base . '/assets/css/style.css">'
        . '</head><body class="error-body">'
        . '<header class="error-header"><a href="' . $base . '/landing.php" class="brand-logo">'
        . '<span class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></span><span>UDOM Student Materials</span></a></header>'
        . '<main class="error-main"><div class="error-card" role="alert">'
        . '<div class="error-icon"><i class="fa-solid ' . $icon . '"></i></div>'
        . '<p class="error-code">Error ' . $e($http_code === 400 && $code === 419 ? 419 : $http_code) . '</p>'
        . '<h1 class="error-title">' . $e($title) . '</h1>'
        . '<p class="error-message">' . $e($message) . '</p>';

    if ($code === 404) {
        echo '<form action="' . $base . '/index.php" method="GET" class="error-search" role="search">'
            . '<label for="error-q" class="visually-hidden">Search study materials</label>'
            . '<input type="search" id="error-q" name="q" class="form-control" placeholder="Search study materials..." maxlength="100">'
            . '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button></form>';
    }

    echo '<div class="error-actions">'
        . '<a href="' . $base . '/landing.php" class="btn btn-primary"><i class="fa-solid fa-house"></i> Home</a>'
        . '<a href="' . $base . '/index.php" class="btn btn-outline"><i class="fa-solid fa-book-open"></i> Study Materials</a>';
    if ($code === 401 || $code === 403) {
        echo '<a href="' . $base . '/account/login.php" class="btn btn-outline"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>';
    }
    if (in_array($code, [408, 429, 500, 502, 503, 504], true)) {
        echo '<a href="" class="btn btn-outline"><i class="fa-solid fa-rotate-right"></i> Try Again</a>';
    }
    echo '</div>';

    if ($reference) {
        echo '<p class="error-reference">Reference: <code>' . $e($reference) . '</code> (quote this if you contact us)</p>';
    }
    echo '<nav class="error-links" aria-label="Help"><a href="' . $base . '/faq.php">FAQ</a><a href="' . $base . '/shared.php">Shared Files</a>'
        . '<a href="' . $base . '/privacy.php">Privacy</a><a href="' . $base . '/terms.php">Terms</a></nav>'
        . '</div></main></body></html>';
    exit;
}
