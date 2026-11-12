<?php
/**
 * Shared Files: every file students chose to make public.
 * Filters: search, file type (PDF, Word, PowerPoint, Images...), category. 12 per page.
 */

require_once __DIR__ . '/config/db.php';

$search = mb_substr(trim(input_text($_GET, 'q')), 0, 100);
$category = normalize_category(input_text($_GET, 'category'));
$type = input_text($_GET, 'type', 'any');
$type = isset(UPLOAD_TYPES[$type]) ? $type : 'any';

$result = get_shared_files_page($pdo, $search, $category, $type, current_page_number());
$files = $result['files'];
$total = $result['total'];

$active_nav = 'shared';
$page_title = 'Shared Files';
$meta_description = 'Notes, slides, past papers and images shared by University of Dodoma students. Filter by PDF, Word, PowerPoint, images and more.';
$meta_canonical = 'shared.php';
$meta_noindex = $search !== '' || $type !== 'any' || $category !== '' || $result['page'] > 1;
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-section hero-compact">
  <div class="container">
    <h1 class="hero-title">Shared by Students</h1>
    <p class="hero-subtitle">Notes, slides, past papers, images and more, shared by UDOM students to help each other learn.</p>

    <div class="search-wrapper">
      <form action="shared.php" method="GET" class="search-form" role="search">
        <?php if ($type !== 'any'): ?><input type="hidden" name="type" value="<?php echo $type; ?>"><?php endif; ?>
        <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?php echo sanitize($category); ?>"><?php endif; ?>
        <label for="shared-search" class="visually-hidden">Search shared files</label>
        <input type="search" name="q" id="shared-search" class="search-input" placeholder="Search shared files..."
          value="<?php echo sanitize($search); ?>" maxlength="100">
        <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i> <span class="search-btn-text">Search</span></button>
      </form>
    </div>
  </div>
</section>

<section class="filter-section">
  <div class="container filter-wrapper">
    <div class="filter-categories">
      <?php foreach (UPLOAD_TYPES as $key => [$label, $icon]): ?>
        <a href="<?php echo sanitize(query_with(['type' => $key === 'any' ? null : $key, 'page' => null])); ?>"
          class="filter-btn <?php echo $type === $key ? 'active' : ''; ?>">
          <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $key === 'any' ? 'All types' : $label; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <form method="GET" action="shared.php" class="shared-category-filter">
      <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?php echo sanitize($search); ?>"><?php endif; ?>
      <?php if ($type !== 'any'): ?><input type="hidden" name="type" value="<?php echo $type; ?>"><?php endif; ?>
      <label for="category" class="visually-hidden">Category</label>
      <select name="category" id="category" class="form-control" data-autosubmit>
        <option value="">All categories</option>
        <?php foreach (get_categories() as $cat): ?>
          <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $category === $cat['name'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn btn-primary btn-sm">Filter</button></noscript>
    </form>
  </div>
</section>

<main class="main-content">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fa-solid fa-share-nodes"></i>
        <?php echo number_format($total); ?> shared file<?php echo $total === 1 ? '' : 's'; ?>
        <?php if ($type !== 'any'): ?> &middot; <?php echo sanitize(UPLOAD_TYPES[$type][0]); ?><?php endif; ?>
        <?php if ($category !== ''): ?> in <?php echo sanitize($category); ?><?php endif; ?>
      </h2>
      <a href="account/upload.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-cloud-arrow-up"></i> Share a file</a>
    </div>

    <div class="shared-grid">
      <?php echo render_shared_grid($files, $search !== '' || $type !== 'any' || $category !== ''
          ? 'No shared files match your filters. Try another type or keyword.'
          : 'No student has shared a file yet. Be the first!'); ?>
    </div>

    <?php echo render_simple_pagination($result['page'], $result['total_pages']); ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
