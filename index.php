<?php
/**
 * UDOM Student Materials - Home Page
 * Lists posts from MySQL with live title search, category filter and pagination (6 per page).
 * Works without JavaScript (normal links/forms); assets/js/main.js upgrades it to live updates.
 */

require_once __DIR__ . '/config/db.php';

// Read and validate the current filters from the URL
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$category = normalize_category($_GET['category'] ?? '');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

// Fetch the requested page of posts
$result = get_posts_page($pdo, $search, $category, $page);
$posts = $result['posts'];
$total_posts = $result['total'];
$page = $result['page'];
$total_pages = $result['total_pages'];

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
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
