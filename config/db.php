<?php
/**
 * Database Configuration & PDO Connection
 * UDOM Student Materials Blog
 *
 * Default XAMPP MySQL credentials: user "root" with an empty password.
 * Change these values if your MySQL setup is different.
 */

$db_host = 'localhost';
$db_name = 'udom_blog';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

// Show dates in Tanzania local time (East Africa Time, UTC+3).
date_default_timezone_set('Africa/Dar_es_Salaam');

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,               // Real prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    // Keep MySQL timestamps (NOW(), CURRENT_TIMESTAMP) in the same time zone as PHP.
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    http_response_code(500);
    die(
        '<div style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:20px;'
        . 'border:1px solid #E5E7EB;border-radius:12px;background:#FFFFFF;color:#111111;">'
        . '<h2 style="color:#0A1E3C;">Database connection failed</h2>'
        . '<p>Make sure MySQL is started in the XAMPP Control Panel and that '
        . '<strong>sql/database.sql</strong> has been imported in phpMyAdmin.</p>'
        . '<p style="color:#666666;font-size:14px;">Details: ' . htmlspecialchars($e->getMessage()) . '</p></div>'
    );
}

// Shared helper functions (categories, rendering, uploads, CSRF).
require_once __DIR__ . '/../includes/functions.php';
