<?php
/**
 * llms.txt (served at /llms.txt through .htaccess) - a plain Markdown summary of the site
 * for AI assistants and LLM-based search (https://llmstxt.org). Generated from the database,
 * public content only.
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$line = fn(string $title, string $path, string $note = '') => '- [' . str_replace(['[', ']'], '', $title) . '](' . site_url($path) . ')' . ($note !== '' ? ': ' . $note : '') . "\n";

echo '# ' . SITE_NAME . "\n\n";
echo '> ' . SITE_DESCRIPTION . "\n\n";
echo "The site is for students of the University of Dodoma (UDOM), Tanzania. Content is in English. "
    . "Study materials are published by the site team; students can also upload files and choose to share them publicly. "
    . "Private student files, accounts and the admin area are not public and must not be accessed.\n\n";

echo "## Main pages\n\n";
echo $line('Home', 'landing.php', 'what the site offers and the latest materials');
echo $line('Study materials', 'index.php', 'searchable list of all posts, filterable by category');
echo $line('Shared by students', 'shared.php', 'files students made public (PDF, Word, PowerPoint, images, more)');
echo $line('FAQ', 'faq.php', 'accounts, uploads, comments, privacy');
echo "\n## Categories\n\n";
foreach (get_categories() as $cat) {
    if ((int) $cat['post_count'] > 0) {
        echo $line($cat['name'], 'index.php?category=' . rawurlencode($cat['name']), meta_excerpt($cat['description'] ?: ($cat['post_count'] . ' posts'), 120));
    }
}
echo "\n## Latest study materials\n\n";
foreach ($pdo->query('SELECT id, title, excerpt, category FROM posts ORDER BY created_at DESC, id DESC LIMIT 50') as $post) {
    echo $line($post['title'], 'post.php?id=' . (int) $post['id'], trim($post['category'] . ' - ' . meta_excerpt($post['excerpt'], 140), ' -'));
}
echo "\n## Policies\n\n";
echo $line('Privacy Policy', 'privacy.php');
echo $line('Terms & Conditions', 'terms.php');
echo $line('Cookie Policy', 'cookies.php');
echo "\n## Optional\n\n";
echo $line('Sitemap (all public URLs)', 'sitemap.xml');
