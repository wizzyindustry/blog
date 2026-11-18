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

    // Optional. "Sign in with Google" - hidden until all three are filled in (see README "Google sign-in (v6)").
    // The client secret stays on the server: it is never sent to the browser, logged or committed.
    'GOOGLE_CLIENT_ID' => '',
    'GOOGLE_CLIENT_SECRET' => '',
    'GOOGLE_REDIRECT_URI' => '', // e.g. 'http://localhost/blog/account/google_callback.php'

    // Email (password reset links). Leave SMTP_HOST empty in development: emails are then saved in
    // logs/mail/ instead of being sent. On a live site ALSO set APP_URL, otherwise no reset links are sent.
    // Gmail: SMTP_HOST smtp.gmail.com, SMTP_PORT 587, SMTP_SECURE tls, SMTP_USER your address,
    // SMTP_PASS a Google "App password" (not your normal password).
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMTP_SECURE' => 'tls', // tls (STARTTLS, port 587) | ssl (port 465) | none (local test servers only)
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'MAIL_FROM' => '', // defaults to SMTP_USER
    'MAIL_FROM_NAME' => 'UDOM Student Materials',

    // Hosting (see DEPLOY_INFINITYFREE.md). Defaults suit this XAMPP computer.
    // Who may open /admin: 'local' (this computer), 'any' (anyone with the admin password),
    // or IP addresses / ranges: '41.59.10.20, 197.250.0.0/16'
    'ADMIN_ACCESS' => 'local',
    // Largest upload the HOST accepts, in MB (InfinityFree: 10). 0 = no extra limit.
    'MAX_UPLOAD_MB' => 0,
    // Behind a host's HTTPS proxy (InfinityFree): trust X-Forwarded-Proto. Never true on a plain server.
    'TRUST_PROXY_HTTPS' => false,
    // Only if every visitor shows the SAME IP address: 'HTTP_X_FORWARDED_FOR' or 'HTTP_X_REAL_IP'
    'TRUST_PROXY_IP_HEADER' => '',
];
