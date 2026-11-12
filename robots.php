<?php
/**
 * robots.txt (served at /robots.txt through .htaccess)
 * Search engines may index the public pages; private areas, APIs and file downloads are excluded.
 * Note: crawlers only read robots.txt at the ROOT of a domain, so this takes full effect when the
 * site is installed at the web root (e.g. https://materials.example.ac.tz/robots.txt).
 */
require_once __DIR__ . '/includes/security.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$base = app_base_path();

echo "# UDOM Student Materials\n";
echo "User-agent: *\n";
foreach (['/admin/', '/account/', '/search-api.php', '/download.php', '/error.php', '/uploads/user_files/', '/uploads/documents/', '/uploads/avatars/', '/*?q=', '/*&q='] as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}
echo 'Allow: ' . $base . "/\n\n";
echo 'Sitemap: ' . site_url('sitemap.xml') . "\n";
