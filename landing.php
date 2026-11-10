<?php
/**
 * UDOM Student Materials - Landing Page
 * Introduces the project: what the site is, what it contains, and how to use it.
 * All numbers and the latest posts are loaded live from MySQL.
 */

require_once __DIR__ . '/config/db.php';

$active_nav = 'landing';
$page_title = 'Welcome';

// Live statistics
$total_posts = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$total_comments = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$last_update = $pdo->query('SELECT MAX(GREATEST(created_at, updated_at)) FROM posts')->fetchColumn();

// Categories with descriptions, icons and post counts (managed by the admin)
$categories = get_categories();

// The 3 newest posts
$latest_posts = $pdo->query(
  'SELECT id, title, slug, category, excerpt, image, image_ratio, image_width, image_height, author, created_at
   FROM posts ORDER BY created_at DESC, id DESC LIMIT 3'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="landing-hero">
  <div class="container landing-hero-inner">
    <div class="landing-hero-text">
      <span class="landing-eyebrow"><i class="fa-solid fa-building-columns"></i> University of Dodoma</span>
      <h1 class="landing-title">Study materials for UDOM students, all in one place</h1>
      <p class="landing-lead">
        UDOM Student Materials is a free blog where students find course notes, design guides, history articles and
        university news. Every post is written and checked by our team and kept up to date.
      </p>

      <!-- Quick search: sends the visitor to the materials page with results -->
      <form action="index.php" method="GET" class="search-form landing-search" role="search">
        <label for="landing-search" class="visually-hidden">Search posts by title</label>
        <input type="search" name="q" id="landing-search" class="search-input"
          placeholder="What do you want to study today?" maxlength="100" autocomplete="off">
        <button type="submit" class="search-btn">
          <i class="fa-solid fa-magnifying-glass"></i> <span class="search-btn-text">Search</span>
        </button>
      </form>

      <div class="landing-actions">
        <a href="index.php" class="btn btn-accent">
          <i class="fa-solid fa-book-open"></i> Browse All Materials
        </a>
        <a href="#how-it-works" class="btn btn-ghost">
          <i class="fa-solid fa-circle-info"></i> How It Works
        </a>
      </div>
    </div>

    <!-- Live stats -->
    <div class="landing-stats" aria-label="Site statistics">
      <div class="landing-stat">
        <i class="fa-solid fa-file-lines"></i>
        <strong><?php echo $total_posts; ?></strong>
        <span>Published posts</span>
      </div>
      <div class="landing-stat">
        <i class="fa-solid fa-folder-tree"></i>
        <strong><?php echo count($categories); ?></strong>
        <span>Categories</span>
      </div>
      <div class="landing-stat">
        <i class="fa-solid fa-comments"></i>
        <strong><?php echo $total_comments; ?></strong>
        <span>Student comments</span>
      </div>
      <div class="landing-stat">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <strong><?php echo $last_update ? date('M d', strtotime($last_update)) : '-'; ?></strong>
        <span>Last updated</span>
      </div>
    </div>
  </div>
</section>

<main class="main-content">

  <!-- About the project -->
  <section class="landing-section">
    <div class="container landing-about">
      <div>
        <h2 class="landing-section-title">What is UDOM Student Materials?</h2>
        <p>
          Finding reliable notes and official information at university can take a lot of time. Materials are spread
          across WhatsApp groups, notice boards and old files. This website brings them together on one page, sorted
          by topic and searchable by title.
        </p>
        <p>
          Every article is stored in a database, so it stays available any time you come back, on your phone or your
          laptop. You can read for free without creating an account, and you can leave a comment or question under
          any post.
        </p>
      </div>
      <ul class="landing-checklist">
        <li><i class="fa-solid fa-circle-check"></i> Free to read, no account needed</li>
        <li><i class="fa-solid fa-circle-check"></i> Works on phones, tablets and computers</li>
        <li><i class="fa-solid fa-circle-check"></i> Instant search as you type</li>
        <li><i class="fa-solid fa-circle-check"></i> Posts reviewed and updated by the team</li>
        <li><i class="fa-solid fa-circle-check"></i> Comment and ask questions on every post</li>
      </ul>
    </div>
  </section>

  <!-- Categories -->
  <section class="landing-section landing-section-alt">
    <div class="container">
      <div class="landing-section-head">
        <h2 class="landing-section-title">What you will find</h2>
        <p class="text-muted">
          Posts are organised into <?php echo count($categories); ?>
          categor<?php echo count($categories) === 1 ? 'y' : 'ies'; ?>. Pick one to start reading.
        </p>
      </div>

      <div class="landing-grid-3">
        <?php foreach ($categories as $cat): ?>
          <a href="<?php echo sanitize(home_url('', $cat['name'])); ?>" class="landing-card landing-card-link">
            <span class="landing-card-icon"><i class="fa-solid <?php echo category_icon($cat['name']); ?>"></i></span>
            <h3><?php echo sanitize($cat['name']); ?></h3>
            <p><?php echo sanitize($cat['description'] ?: 'Browse all posts in ' . $cat['name'] . '.'); ?></p>
            <span class="landing-card-footer">
              <?php $n = (int) $cat['post_count']; echo $n . ' post' . ($n === 1 ? '' : 's'); ?>
              <i class="fa-solid fa-arrow-right"></i>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- How it works -->
  <section class="landing-section" id="how-it-works">
    <div class="container">
      <div class="landing-section-head">
        <h2 class="landing-section-title">How it works</h2>
        <p class="text-muted">Four simple steps from question to answer.</p>
      </div>

      <ol class="landing-steps">
        <li class="landing-card">
          <span class="landing-step-number">1</span>
          <h3><i class="fa-solid fa-magnifying-glass"></i> Search</h3>
          <p>Type a topic in the search bar. Results appear instantly while you type.</p>
        </li>
        <li class="landing-card">
          <span class="landing-step-number">2</span>
          <h3><i class="fa-solid fa-filter"></i> Filter</h3>
          <p>Narrow the list to a single category with one click.</p>
        </li>
        <li class="landing-card">
          <span class="landing-step-number">3</span>
          <h3><i class="fa-solid fa-book-open-reader"></i> Read</h3>
          <p>Open a post to read the full material, then share it with classmates.</p>
        </li>
        <li class="landing-card">
          <span class="landing-step-number">4</span>
          <h3><i class="fa-solid fa-comments"></i> Discuss</h3>
          <p>Leave a comment or question under the post to help other students.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- Latest posts (live from the database) -->
  <section class="landing-section landing-section-alt">
    <div class="container">
      <div class="landing-section-head landing-section-head-row">
        <div>
          <h2 class="landing-section-title">Latest materials</h2>
          <p class="text-muted">The newest posts added to the site.</p>
        </div>
        <a href="index.php" class="btn btn-outline btn-sm">
          View all posts <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>

      <div class="posts-grid">
        <?php if ($latest_posts): ?>
          <?php echo render_posts_grid($latest_posts); ?>
        <?php else: ?>
          <div class="empty-state" style="grid-column: 1 / -1;">
            <div class="empty-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <h3 class="empty-title">New materials are coming soon</h3>
            <p class="empty-desc">Our team is preparing the first posts. Please check back shortly.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Call to action -->
  <section class="landing-section">
    <div class="container">
      <div class="landing-cta">
        <div>
          <h2>Ready to start studying?</h2>
          <p>Browse every post, or search for the topic you need right now.</p>
        </div>
        <a href="index.php" class="btn btn-accent">
          <i class="fa-solid fa-book-open"></i> Explore Materials
        </a>
      </div>
    </div>
  </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
