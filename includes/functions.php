<?php
/**
 * Shared Helper Functions
 * UDOM Student Materials Blog
 *
 * Loaded automatically by config/db.php, so every page that connects to the
 * database also has these helpers available.
 */

/* --------------------------------------------------------------
 * Site-wide settings
 * ------------------------------------------------------------ */

// Categories are stored in the `categories` table and managed by the admin
// (admin/categories.php). See get_categories() below.

// Icons the admin can choose for a category (Font Awesome 6 class => label).
const CATEGORY_ICONS = [
    'fa-folder' => 'Folder',
    'fa-bullhorn' => 'Announcement',
    'fa-compass-drafting' => 'Design',
    'fa-landmark' => 'History',
    'fa-book' => 'Book',
    'fa-graduation-cap' => 'Graduation',
    'fa-flask' => 'Science',
    'fa-calculator' => 'Mathematics',
    'fa-laptop-code' => 'Computing',
    'fa-microchip' => 'Technology',
    'fa-scale-balanced' => 'Law',
    'fa-stethoscope' => 'Health',
    'fa-chart-line' => 'Business',
    'fa-language' => 'Languages',
    'fa-earth-africa' => 'Geography',
    'fa-seedling' => 'Agriculture',
    'fa-palette' => 'Arts',
    'fa-music' => 'Music',
    'fa-futbol' => 'Sports',
    'fa-calendar-days' => 'Events',
    'fa-briefcase' => 'Careers',
    'fa-lightbulb' => 'Tips',
    'fa-people-group' => 'Student Life',
    'fa-newspaper' => 'News',
];

// Posts shown per page on the home page.
const POSTS_PER_PAGE = 6;

// Maximum upload size for post images (5 MB).
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

// Image used when a post has no uploaded image.
const DEFAULT_POST_IMAGE = 'default-post.svg';

// Display ratios the admin can choose for a post image.
// 'auto' frames the image in its own shape (from its real width/height).
// key => [label, shape hint, width, height]
const IMAGE_RATIOS = [
    'auto' => ['Original', 'Matches the image', 0, 0],
    '16:9' => ['16:9', 'Wide landscape', 16, 9],
    '4:3' => ['4:3', 'Landscape', 4, 3],
    '1:1' => ['1:1', 'Square', 1, 1],
    '4:5' => ['4:5', 'Portrait', 4, 5],
    '3:4' => ['3:4', 'Portrait', 3, 4],
    '9:16' => ['9:16', 'Tall portrait', 9, 16],
];

// Ratio used when an image's size is unknown (sample images are 16:9).
const FALLBACK_IMAGE_RATIO = 16 / 9;

/* --------------------------------------------------------------
 * Output & text helpers
 * ------------------------------------------------------------ */

/**
 * Escape a value for safe output inside HTML (prevents XSS).
 */
if (!function_exists('sanitize')) {
    function sanitize($data): string
    {
        return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Turn a title into a URL-friendly slug, e.g. "Hello World!" -> "hello-world".
 */
function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    if ($converted !== false) {
        $text = $converted;
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = strtolower(trim(preg_replace('~-+~', '-', $text), '-'));
    return $text !== '' ? $text : 'post-' . time();
}

/**
 * Build a slug that does not already exist in the posts table.
 * Pass $exclude_id when editing so a post does not clash with itself.
 */
function unique_slug(PDO $pdo, string $title, int $exclude_id = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $counter = 2;
    $check = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE slug = :slug AND id <> :id');

    while (true) {
        $check->execute([':slug' => $slug, ':id' => $exclude_id]);
        if ((int) $check->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $counter++;
    }
}

/* --------------------------------------------------------------
 * Categories (stored in the `categories` table)
 * ------------------------------------------------------------ */

/**
 * All categories with their post counts, sorted by name.
 * Loaded once per request; pass true to reload after a change.
 *
 * Each row: id, name, description, icon, created_at, post_count
 */
function get_categories(bool $refresh = false): array
{
    global $pdo;
    static $cache = null;

    if ($cache === null || $refresh) {
        $cache = $pdo->query(
            'SELECT c.id, c.name, c.description, c.icon, c.created_at, COUNT(p.id) AS post_count
             FROM categories c
             LEFT JOIN posts p ON p.category = c.name
             GROUP BY c.id, c.name, c.description, c.icon, c.created_at
             ORDER BY c.name ASC'
        )->fetchAll();
    }
    return $cache;
}

/**
 * Just the category names, e.g. ['Design', 'History', 'UDOM News'].
 */
function category_names(): array
{
    return array_column(get_categories(), 'name');
}

/**
 * One category row by name (case-insensitive), or null.
 */
function find_category(string $name): ?array
{
    foreach (get_categories() as $cat) {
        if (strcasecmp($cat['name'], trim($name)) === 0) {
            return $cat;
        }
    }
    return null;
}

/**
 * Return the exact stored category name for user input (case-insensitive),
 * or an empty string if no such category exists (meaning "All").
 */
function normalize_category(?string $raw): string
{
    $cat = find_category((string) $raw);
    return $cat ? $cat['name'] : '';
}

/**
 * Font Awesome icon class for a category (falls back to a folder icon).
 */
function category_icon(string $category): string
{
    $cat = find_category($category);
    $icon = $cat['icon'] ?? 'fa-folder';
    return isset(CATEGORY_ICONS[$icon]) ? $icon : 'fa-folder';
}

/**
 * Read and validate the category form fields from $_POST.
 * $exclude_id is the category being edited (so it does not clash with itself).
 *
 * Returns [$data, $errors].
 */
function validate_category_input(PDO $pdo, int $exclude_id = 0): array
{
    $data = [
        // Collapse repeated spaces: "UDOM   News" -> "UDOM News"
        'name' => preg_replace('/\s+/u', ' ', trim((string) ($_POST['name'] ?? ''))),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'icon' => (string) ($_POST['icon'] ?? 'fa-folder'),
    ];
    $errors = [];

    $len = mb_strlen($data['name']);
    if ($len === 0) {
        $errors['name'] = 'Category name is required.';
    } elseif ($len < 2 || $len > 50) {
        $errors['name'] = 'Category name must be between 2 and 50 characters.';
    } elseif (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} &\'.,-]*$/u', $data['name'])) {
        $errors['name'] = 'Use letters, numbers, spaces and & \' . , - only.';
    } else {
        // Names are unique, ignoring upper/lower case
        $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(:name) AND id <> :id');
        $check->execute([':name' => $data['name'], ':id' => $exclude_id]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['name'] = 'A category with this name already exists.';
        }
    }

    if (mb_strlen($data['description']) > 300) {
        $errors['description'] = 'Description cannot be longer than 300 characters.';
    }

    if (!isset(CATEGORY_ICONS[$data['icon']])) {
        $errors['icon'] = 'Please choose an icon from the list.';
    }

    return [$data, $errors];
}

/**
 * Relative URL to a post image inside /uploads.
 * $prefix is '../' when called from the admin folder.
 */
function post_image_url(?string $image, string $prefix = ''): string
{
    $file = $image ? basename($image) : DEFAULT_POST_IMAGE;
    return $prefix . 'uploads/' . rawurlencode($file);
}

/**
 * Width / height ratio used to frame a post's image, e.g. 1.777 for 16:9,
 * 0.75 for 3:4 portrait. 'auto' uses the image's own size when known.
 */
function post_image_ratio(array $post): float
{
    $key = $post['image_ratio'] ?? 'auto';

    if ($key !== 'auto' && isset(IMAGE_RATIOS[$key])) {
        return IMAGE_RATIOS[$key][2] / IMAGE_RATIOS[$key][3];
    }

    $w = (int) ($post['image_width'] ?? 0);
    $h = (int) ($post['image_height'] ?? 0);
    if ($w > 0 && $h > 0) {
        // Keep extreme panoramas / very tall images within a sensible range
        return max(0.4, min(3.0, $w / $h));
    }
    return FALLBACK_IMAGE_RATIO;
}

/**
 * Inline style that passes the ratio to CSS (used as var(--ratio)).
 */
function post_image_style(array $post): string
{
    return '--ratio: ' . round(post_image_ratio($post), 4) . ';';
}

/**
 * "landscape", "portrait" or "square", used as a CSS class.
 */
function post_image_orientation(array $post): string
{
    $r = post_image_ratio($post);
    return $r > 1.05 ? 'landscape' : ($r < 0.95 ? 'portrait' : 'square');
}

/**
 * Clean admin-written post HTML before displaying it.
 * Keeps common formatting tags, removes scripts, event handlers
 * (onclick=...) and javascript: links.
 * Plain text without any tags is converted into paragraphs.
 */
function clean_post_html(?string $html): string
{
    $html = (string) $html;

    // Plain text: split on blank lines into <p> blocks.
    if ($html === strip_tags($html)) {
        $paragraphs = preg_split('/\R{2,}/', trim($html));
        $out = '';
        foreach ($paragraphs as $p) {
            $out .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        return $out;
    }

    $allowed = '<p><br><h2><h3><h4><strong><b><em><i><u><ul><ol><li><blockquote>'
        . '<a><code><pre><hr><table><thead><tbody><tr><th><td><img>';
    $html = strip_tags($html, $allowed);

    // Remove inline event handlers such as onclick="..." / onerror=...
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

    // Neutralise dangerous URL schemes in href/src attributes.
    $html = preg_replace(
        '/\b(href|src)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data)\s*:[^"\'>\s]*\2/i',
        '$1="#"',
        $html
    );

    return $html;
}

/* --------------------------------------------------------------
 * Posts query (shared by index.php and search-api.php)
 * ------------------------------------------------------------ */

/**
 * Fetch one page of posts with optional title search and category filter.
 *
 * Returns: ['posts' => [...], 'total' => int, 'page' => int, 'total_pages' => int]
 */
function get_posts_page(PDO $pdo, string $search, string $category, int $page, int $per_page = POSTS_PER_PAGE): array
{
    $where = [];
    $params = [];

    if ($search !== '') {
        // Escape LIKE wildcards so "%" or "_" typed by the user are literal.
        $where[] = 'title LIKE :q';
        $params[':q'] = '%' . addcslashes($search, '%_\\') . '%';
    }
    if ($category !== '') {
        $where[] = 'category = :category';
        $params[':category'] = $category;
    }
    $where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // Total matching rows, for pagination.
    $count = $pdo->prepare('SELECT COUNT(*) FROM posts' . $where_sql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min(max(1, $page), $total_pages);
    $offset = ($page - 1) * $per_page;

    $stmt = $pdo->prepare(
        'SELECT id, title, slug, category, excerpt, image, image_ratio, image_width, image_height, author, created_at FROM posts'
        . $where_sql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'posts' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'total_pages' => $total_pages,
    ];
}

/* --------------------------------------------------------------
 * HTML rendering (server-side, reused by the live-search API so the
 * page and the JavaScript always show identical markup)
 * ------------------------------------------------------------ */

/**
 * Markup for one post card in the home page grid.
 */
function render_post_card(array $post): string
{
    $id = (int) $post['id'];
    $title = sanitize($post['title']);
    $author = sanitize($post['author'] ?: 'UDOM Student Materials');
    $date = date('M d, Y', strtotime($post['created_at']));

    return '
      <article class="post-card">
        <div class="post-card-thumb is-' . post_image_orientation($post) . '" style="' . post_image_style($post) . '">
          <img src="' . post_image_url($post['image']) . '" alt="' . $title . '" loading="lazy"
            onerror="this.onerror=null;this.src=\'uploads/' . DEFAULT_POST_IMAGE . '\';">
          <span class="category-badge">' . sanitize($post['category']) . '</span>
        </div>
        <div class="post-card-body">
          <h2 class="post-card-title"><a href="post.php?id=' . $id . '">' . $title . '</a></h2>
          <p class="post-card-excerpt">' . sanitize($post['excerpt']) . '</p>
          <div class="post-card-meta">
            <span class="post-meta-item"><i class="fa-regular fa-calendar"></i> ' . $date . '</span>
            <span class="post-meta-item"><i class="fa-solid fa-user"></i> ' . $author . '</span>
          </div>
          <div class="post-card-footer">
            <a href="post.php?id=' . $id . '" class="btn btn-primary btn-sm">
              Read More <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </article>';
}

/**
 * Markup for the whole grid (cards, or an empty state when nothing matches).
 */
function render_posts_grid(array $posts): string
{
    if (!$posts) {
        return '
      <div class="empty-state" style="grid-column: 1 / -1;">
        <div class="empty-icon"><i class="fa-solid fa-book-open-reader"></i></div>
        <h3 class="empty-title">No Materials Found</h3>
        <p class="empty-desc">No posts match your search or category. Try a different keyword.</p>
        <a href="index.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All Posts</a>
      </div>';
    }
    return implode('', array_map('render_post_card', $posts));
}

/**
 * Build an index.php URL for the given search state.
 */
function home_url(string $search, string $category, int $page = 1): string
{
    $args = [];
    if ($search !== '') {
        $args['q'] = $search;
    }
    if ($category !== '') {
        $args['category'] = $category;
    }
    if ($page > 1) {
        $args['page'] = $page;
    }
    return 'index.php' . ($args ? '?' . http_build_query($args) : '');
}

/**
 * Pagination links. Each link carries data-page so JavaScript can
 * intercept it; without JavaScript the normal href still works.
 */
function render_pagination(int $page, int $total_pages, string $search, string $category): string
{
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<nav class="pagination-wrapper" aria-label="Page navigation">';

    if ($page > 1) {
        $html .= '<a href="' . sanitize(home_url($search, $category, $page - 1)) . '" data-page="' . ($page - 1)
            . '" class="page-link" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>';
    } else {
        $html .= '<span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>';
    }

    for ($p = 1; $p <= $total_pages; $p++) {
        $active = $p === $page ? ' active' : '';
        $html .= '<a href="' . sanitize(home_url($search, $category, $p)) . '" data-page="' . $p
            . '" class="page-link' . $active . '"' . ($active ? ' aria-current="page"' : '') . '>' . $p . '</a>';
    }

    if ($page < $total_pages) {
        $html .= '<a href="' . sanitize(home_url($search, $category, $page + 1)) . '" data-page="' . ($page + 1)
            . '" class="page-link" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>';
    }

    return $html . '</nav>';
}

/**
 * Short status line above the grid, e.g. 'Showing 2 results for "design" in History'.
 */
function render_results_summary(int $total, string $search, string $category): string
{
    if ($search === '' && $category === '') {
        return 'Showing ' . $total . ' published post' . ($total === 1 ? '' : 's');
    }

    $html = 'Showing ' . $total . ' result' . ($total === 1 ? '' : 's');
    if ($search !== '') {
        $html .= ' for "<strong>' . sanitize($search) . '</strong>"';
    }
    if ($category !== '') {
        $html .= ' in <strong>' . sanitize($category) . '</strong>';
    }
    return $html . ' &bull; <a href="index.php" class="text-muted"><i class="fa-solid fa-xmark"></i> Clear filters</a>';
}

/* --------------------------------------------------------------
 * Post form validation (admin create / edit)
 * ------------------------------------------------------------ */

/**
 * Read and validate the post form fields from $_POST.
 *
 * Returns [$data, $errors] where $data holds the trimmed values and
 * $errors maps field name => message (empty when everything is valid).
 */
function validate_post_input(): array
{
    $data = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'category' => normalize_category($_POST['category'] ?? ''),
        'author' => trim((string) ($_POST['author'] ?? '')),
        'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
        'content' => trim((string) ($_POST['content'] ?? '')),
        'image_ratio' => (string) ($_POST['image_ratio'] ?? 'auto'),
    ];
    $errors = [];

    $title_len = mb_strlen($data['title']);
    if ($title_len === 0) {
        $errors['title'] = 'Title is required.';
    } elseif ($title_len < 5 || $title_len > 255) {
        $errors['title'] = 'Title must be between 5 and 255 characters.';
    }

    if ($data['category'] === '') {
        $errors['category'] = category_names()
            ? 'Please choose a category from the list.'
            : 'There are no categories yet. Add one on the Categories page first.';
    }

    if (mb_strlen($data['author']) > 100) {
        $errors['author'] = 'Author name cannot be longer than 100 characters.';
    }

    $excerpt_len = mb_strlen($data['excerpt']);
    if ($excerpt_len === 0) {
        $errors['excerpt'] = 'Excerpt is required.';
    } elseif ($excerpt_len < 10 || $excerpt_len > 500) {
        $errors['excerpt'] = 'Excerpt must be between 10 and 500 characters.';
    }

    if ($data['content'] === '') {
        $errors['content'] = 'Content is required.';
    } elseif (mb_strlen(trim(strip_tags($data['content']))) < 20) {
        $errors['content'] = 'Content must contain at least 20 characters of text.';
    }

    if (!isset(IMAGE_RATIOS[$data['image_ratio']])) {
        $errors['image_ratio'] = 'Please choose how the image should be displayed.';
    }

    if ($data['author'] === '') {
        $data['author'] = 'UDOM Student Materials';
    }

    return [$data, $errors];
}

/* --------------------------------------------------------------
 * Image uploads
 * ------------------------------------------------------------ */

/**
 * Validate and store an uploaded post image.
 *
 * Returns the new file name, or null when no file was chosen.
 * On a validation problem, returns null and sets $error.
 * $size receives [width, height] in pixels of the saved image.
 * The real file type is checked from its contents (not just the extension),
 * and only JPG, PNG, WEBP and GIF images are accepted.
 */
function handle_image_upload(string $field, ?string &$error, ?array &$size = null): ?string
{
    $error = null;
    $size = null;

    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The image is too large. Maximum size is 5 MB.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The image could not be uploaded (error code ' . (int) $file['error'] . '). Please try again.';
        return null;
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        $error = 'The image is too large. Maximum size is 5 MB.';
        return null;
    }

    // Detect the real image type from the file contents.
    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if ($info === false || !isset($allowed[$info['mime']])) {
        $error = 'Invalid image. Please upload a JPG, PNG, WEBP or GIF file.';
        return null;
    }

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        $error = 'The uploads folder is missing and could not be created.';
        return null;
    }

    // Random file name: never trust the original name.
    $new_name = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info['mime']];

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
        $error = 'The image could not be saved on the server. Check that the uploads folder is writable.';
        return null;
    }

    // Real pixel size, used to frame the image in its own shape (ratio "Original")
    $size = [(int) $info[0], (int) $info[1]];

    return $new_name;
}

/**
 * Delete an uploaded post image from disk.
 * Only files created by handle_image_upload() (prefix "post_") are removed,
 * so the bundled sample images are never deleted.
 */
function delete_uploaded_image(?string $file_name): void
{
    if (!$file_name) {
        return;
    }
    $file_name = basename($file_name);
    if (strpos($file_name, 'post_') !== 0) {
        return;
    }
    $path = __DIR__ . '/../uploads/' . $file_name;
    if (is_file($path)) {
        @unlink($path);
    }
}

/* --------------------------------------------------------------
 * Sessions & CSRF protection (admin forms)
 * ------------------------------------------------------------ */

/**
 * Start the session once, with safer cookie settings.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

/**
 * Per-session token that must be sent back with every admin form.
 */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden form input containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * True when the submitted form carries the correct CSRF token.
 */
function csrf_valid(): bool
{
    start_session();
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Store a one-time message to show after a redirect.
 */
function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][$type] = $message;
}

/**
 * Read and clear a one-time message.
 */
function get_flash(string $type): string
{
    start_session();
    $message = $_SESSION['flash'][$type] ?? '';
    unset($_SESSION['flash'][$type]);
    return $message;
}
