<?php
/**
 * Health check: GET /blog/health.php
 * Used by deploy/deploy.ps1 (blue-green switch + automatic rollback) and by any uptime monitor.
 *
 *   200 {"status":"ok"}     everything works
 *   503 {"status":"fail"}   something is broken (database down, folder not writable, ...)
 *
 * Visitors from other computers only see the status. Requests from this computer also get the
 * individual checks and which release (blue / green, version) is answering.
 */
require_once __DIR__ . '/includes/security.php'; // Secrets + headers only: the database is checked below
date_default_timezone_set('Africa/Dar_es_Salaam');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$checks = [];
$ok = true;
$fail = function (string $name, string $why) use (&$checks, &$ok) {
    $checks[$name] = 'fail: ' . $why;
    $ok = false;
};

// PHP version and the extensions the site needs
$checks['php'] = PHP_VERSION;
foreach (['pdo_mysql', 'openssl', 'curl', 'mbstring', 'fileinfo', 'json'] as $ext) {
    if (!extension_loaded($ext)) {
        $fail('ext_' . $ext, 'missing');
    }
}

// Database: connect with the site's own account, and check the tables of the latest upgrade exist
try {
    $pdo = new PDO(
        'mysql:host=' . app_config('DB_HOST', 'localhost') . ';dbname=' . app_config('DB_NAME', 'udom_blog') . ';charset=utf8mb4',
        (string) app_config('DB_USER', ''), (string) app_config('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->query('SELECT 1 FROM users LIMIT 1');
    $pdo->query('SELECT 1 FROM password_resets LIMIT 1'); // v7 schema present
    $checks['database'] = 'ok';
} catch (Throwable $e) {
    error_log('health.php: database check failed: ' . $e->getMessage());
    $fail('database', 'cannot query (see logs/php-errors.log)');
}

// Storage the site writes to
foreach (['uploads/user_files', 'uploads/avatars', 'logs'] as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path) || !is_writable($path)) {
        $fail('writable_' . str_replace('/', '_', $dir), 'not writable');
    }
}
if (!isset($checks['writable_logs'])) {
    $checks['storage'] = 'ok';
}

// Which release is answering (written by deploy.ps1; absent in a plain development copy)
$release = null;
if (is_file(__DIR__ . '/RELEASE.json')) {
    $release = json_decode((string) file_get_contents(__DIR__ . '/RELEASE.json'), true);
}

http_response_code($ok ? 200 : 503);
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
echo json_encode($local
    ? ['status' => $ok ? 'ok' : 'fail', 'checks' => $checks, 'release' => $release, 'time' => date('c')]
    : ['status' => $ok ? 'ok' : 'fail'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
