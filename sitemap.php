<?php
/**
 * sitemap.xml (served at /sitemap.xml through .htaccess)
 * Lists every public page, post, category and publicly shared file, generated from the database.
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$urls = [];
$add = function (string $path, ?string $lastmod = null, string $freq = 'weekly', string $priority = '0.5') use (&$urls) {
    $urls[] = [site_url($path), $lastmod ? date('Y-m-d', strtotime($lastmod)) : null, $freq, $priority];
};

$latest_post = $pdo->query('SELECT MAX(COALESCE(updated_at, created_at)) FROM posts')->fetchColumn();
$add('landing.php', $latest_post, 'daily', '1.0');
$add('index.php', $latest_post, 'daily', '0.9');
$add('shared.php', null, 'daily', '0.8');
$add('faq.php', null, 'monthly', '0.4');
$add('privacy.php', LEGAL_VERSION, 'yearly', '0.2');
$add('terms.php', LEGAL_VERSION, 'yearly', '0.2');
$add('cookies.php', LEGAL_VERSION, 'yearly', '0.2');

foreach (get_categories() as $cat) {
    if ((int) $cat['post_count'] > 0) {
        $add('index.php?category=' . rawurlencode($cat['name']), null, 'weekly', '0.6');
    }
}
foreach ($pdo->query('SELECT id, COALESCE(updated_at, created_at) AS m FROM posts ORDER BY id DESC LIMIT 20000') as $row) {
    $add('post.php?id=' . (int) $row['id'], $row['m'], 'monthly', '0.8');
}
foreach ($pdo->query("SELECT f.id, f.created_at FROM user_files f JOIN users u ON u.id = f.user_id
                      WHERE f.visibility = 'public' AND u.status = 'active' ORDER BY f.id DESC LIMIT 20000") as $row) {
    $add('shared_file.php?id=' . (int) $row['id'], $row['created_at'], 'monthly', '0.5');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $lastmod, $freq, $priority]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
        . ($lastmod ? '<lastmod>' . $lastmod . '</lastmod>' : '')
        . '<changefreq>' . $freq . '</changefreq><priority>' . $priority . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
