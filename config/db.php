<?php
/**
 * Database Configuration & PDO Connection
 * UDOM Student Materials Blog
 *
 * Credentials are NOT stored here. They come from config/secrets.php (git-ignored)
 * or environment variables - see config/secrets.example.php.
 * The site connects as "udom_app", a MySQL account that can only read and write
 * rows in udom_blog (no DROP, ALTER, GRANT or access to other databases).
 */

// Security layer first: debug off, HTTPS, security headers, rate limiting, sanitizer.
require_once __DIR__ . '/../includes/security.php';

// Show dates in Tanzania local time (East Africa Time, UTC+3).
date_default_timezone_set('Africa/Dar_es_Salaam');

$dsn = 'mysql:host=' . app_config('DB_HOST', 'localhost') . ';dbname=' . app_config('DB_NAME', 'udom_blog') . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,               // Real prepared statements (no SQL injection)
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,         // One statement per query
];

try {
    $pdo = new PDO($dsn, (string) app_config('DB_USER', ''), (string) app_config('DB_PASS', ''), $options);
    // Keep MySQL timestamps (NOW(), CURRENT_TIMESTAMP) in the same time zone as PHP.
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    // Details go to the server log only; visitors never see hostnames, users or SQL errors.
    $ref = strtoupper(bin2hex(random_bytes(4)));
    error_log('[ref ' . $ref . '] Database connection failed: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'The site is temporarily unavailable (database). Reference ' . $ref . PHP_EOL);
        exit(1);
    }
    render_error_page(503, 'The site is temporarily unavailable', 'We cannot reach the database right now. Please try again in a few minutes.', $ref);
}

// Shared helper functions (categories, rendering, uploads, CSRF).
require_once __DIR__ . '/../includes/functions.php';

// Document attachments: upload checks, previews (PDF, Word, PowerPoint).
require_once __DIR__ . '/../includes/documents.php';

// User accounts, user uploads, activity tracking, pagination helpers.
require_once __DIR__ . '/../includes/users.php';

// Comments, replies and who may edit / delete them.
require_once __DIR__ . '/../includes/comments.php';

// <head> tags: meta description, social cards, favicons, consent-aware fonts.
require_once __DIR__ . '/../includes/seo.php';
