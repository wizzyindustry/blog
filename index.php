<?php
/**
 * UDOM Student Materials - Home Page
 * Lists posts from MySQL with live title search, category filter and pagination (6 per page).
 * Works without JavaScript (normal links/forms); assets/js/main.js upgrades it to live updates.
 */

require_once __DIR__ . '/config/db.php';

// Read and validate the current filters from the URL
$search = mb_substr(trim(input_text($_GET, 'q')), 0, 100);
$category = normalize_category(input_text($_GET, 'category'));
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

// Fetch the requested page of posts
$result = get_posts_page($pdo, $search, $category, $page);
$posts = $result['posts'];
$total_posts = $result['total'];
$page = $result['page'];
$total_pages = $result['total_pages'];

// Files students shared publicly, filtered by the same search and category
try {
  $shared = get_shared_files_page($pdo, $search, $category, 'any', 1, 8);
} catch (PDOException $e) {
  $shared = ['files' => [], 'total' => 0]; // Sharing tables not installed yet
}

// Total for the "All" button (each category row already has its own post_count)
$all_total = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();

// Header highlights: "Categories" menu + the current category inside it
$active_nav = $category !== '' ? 'categories' : 'home';
$active_category = $category;
$page_title = $category !== '' ? $category : 'Home';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with Search -->
<section class="hero-section">
  <div class="container">
    <h1 class="hero-title">UDOM Student Materials</h1>
    <p class="hero-subtitle">
      Study materials, design notes, history articles and official University of Dodoma news, all in one place.
    </p>

    <div class="search-wrapper">
      <form action="index.php" method="GET" class="search-form" id="search-form" role="search">
        <?php if ($category !== ''): ?>
          <input type="hidden" name="category" value="<?php echo sanitize($category); ?>" id="search-category">
        <?php endif; ?>
        <label for="live-search-input" class="visually-hidden">Search posts by title</label>
        <input type="search" name="q" id="live-search-input" class="search-input"
          placeholder="Search posts by title..." value="<?php echo sanitize($search); ?>" autocomplete="off"
          maxlength="100">
        <button type="submit" class="search-btn">
          <i class="fa-solid fa-magnifying-glass"></i> <span class="search-btn-text">Search</span>
        </button>
      </form>
    </div>
  </div>
</section>

<!-- Category Filter -->
<section class="filter-section">
  <div class="container filter-wrapper">
    <div class="filter-categories">
      <a href="<?php echo sanitize(home_url($search, '')); ?>"
        class="filter-btn <?php echo $category === '' ? 'active' : ''; ?>" data-category="">
        <i class="fa-solid fa-layer-group"></i> All
        <span class="filter-count"><?php echo $all_total; ?></span>
      </a>
      <?php /* One button per category in the database */ ?>
      <?php foreach (get_categories() as $cat): ?>
        <a href="<?php echo sanitize(home_url($search, $cat['name'])); ?>"
          class="filter-btn <?php echo $category === $cat['name'] ? 'active' : ''; ?>"
          data-category="<?php echo sanitize($cat['name']); ?>">
          <i class="fa-solid <?php echo category_icon($cat['name']); ?>"></i> <?php echo sanitize($cat['name']); ?>
          <span class="filter-count"><?php echo (int) $cat['post_count']; ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="filter-status" id="results-count" aria-live="polite">
      <?php echo render_results_summary($total_posts, $search, $category); ?>
    </div>
  </div>
</section>

<!-- Posts Grid -->
<main class="main-content">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fa-solid fa-book-open"></i> Latest Posts
      </h2>
      <span class="text-muted" id="page-indicator">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
    </div>

    <!-- data-* attributes give main.js the starting state -->
    <div class="posts-grid" id="posts-grid" data-page="<?php echo $page; ?>"
      data-category="<?php echo sanitize($category); ?>">
      <?php echo render_posts_grid($posts); ?>
    </div>

    <!-- Pagination (6 posts per page) -->
    <div id="pagination-wrapper">
      <?php echo render_pagination($page, $total_pages, $search, $category); ?>
    </div>

    <!-- Files shared by students (public uploads). Updated by live search too. -->
    <section class="shared-section" aria-labelledby="shared-heading">
      <div class="section-header">
        <h2 class="section-title" id="shared-heading">
          <i class="fa-solid fa-share-nodes"></i> Shared by Students
          <span class="section-count" id="shared-count"><?php echo number_format($shared['total']); ?></span>
        </h2>
        <div class="section-actions">
          <a href="account/upload.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-cloud-arrow-up"></i> Share a file</a>
          <a href="<?php echo sanitize('shared.php' . query_with(['page' => null])); ?>" class="btn btn-primary btn-sm" id="shared-all-link">
            View all <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>
      </div>

      <nav class="type-chips" aria-label="Shared file types">
        <?php foreach (UPLOAD_TYPES as $key => [$label, $icon]): ?>
          <a href="shared.php?type=<?php echo $key; ?><?php echo $category !== '' ? '&amp;category=' . rawurlencode($category) : ''; ?>" class="type-chip type-<?php echo $key; ?>">
            <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $key === 'any' ? 'All files' : $label; ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="shared-grid" id="shared-grid">
        <?php echo render_shared_grid($shared['files'], $search !== '' || $category !== '' ? 'No shared files match your search or category.' : 'No student has shared a file yet. Be the first!'); ?>
      </div>
    </section>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
