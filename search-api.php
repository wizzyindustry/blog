<?php
/**
 * Live Search & Filter API
 * Called by assets/js/main.js with ?q=&category=&page=
 * Returns JSON containing ready-to-insert HTML rendered by the same PHP
 * functions as index.php, so live results look identical to page loads.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config/db.php';

$search = trim(input_text($_GET, 'q'));
$search = mb_substr($search, 0, 100);
$category = normalize_category(input_text($_GET, 'category'));
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

// At most 120 live searches per minute per IP address (stops scraping / flooding the database)
if (rate_limited('search', client_ip(), 120, 1)) {
    too_many_requests('Too many searches. Please slow down.', true);
}
rate_hit('search', client_ip());

try {
    $result = get_posts_page($pdo, $search, $category, $page);
    $shared = get_shared_files_page($pdo, $search, $category, 'any', 1, 8);
    $shared_query = http_build_query(array_filter(['q' => $search, 'category' => $category], 'strlen'));

    echo json_encode([
        'status' => 'success',
        'total' => $result['total'],
        'page' => $result['page'],
        'total_pages' => $result['total_pages'],
        'url' => home_url($search, $category, $result['page']),
        'html' => render_posts_grid($result['posts']),
        'pagination' => render_pagination($result['page'], $result['total_pages'], $search, $category),
        'summary' => render_results_summary($result['total'], $search, $category),
        // Files shared by students for the same search / category
        'shared_html' => render_shared_grid($shared['files'], 'No shared files match your search or category.'),
        'shared_total' => number_format($shared['total']),
        'shared_url' => 'shared.php' . ($shared_query !== '' ? '?' . $shared_query : ''),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not load posts. Please try again.']);
}
