<?php
/**
 * Template for config/secrets.php (copy it, then fill in real values).
 * config/secrets.php is in .gitignore and must never be committed.
 *
 * Create the least-privilege database account once, as MySQL root:
 *   CREATE USER 'udom_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
 *   GRANT SELECT, INSERT, UPDATE, DELETE ON udom_blog.* TO 'udom_app'@'localhost';
 *
 * Environment variables with the same names (DB_HOST, DB_NAME, DB_USER, DB_PASS,
 * FORCE_HTTP, FORCE_HTTPS) override these values, e.g. on a hosting control panel.
 */
return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'udom_blog',
    'DB_USER' => 'udom_app',
    'DB_PASS' => 'CHANGE-ME',

    // Protocol. Not live yet: HTTP only (HTTPS requests are sent to HTTP).
    // When going live with a real certificate: FORCE_HTTP => false, FORCE_HTTPS => true (and see .htaccess).
    'FORCE_HTTP' => true,
    'FORCE_HTTPS' => false,

    // Optional. Public address used in sitemap.xml, robots.txt, llms.txt and social cards
    // (detected automatically when empty), e.g. 'https://materials.example.ac.tz'
    'APP_URL' => '',
    // Optional. Shown on the Privacy, Terms, Cookie and FAQ pages as the contact address
    'CONTACT_EMAIL' => '',
];
