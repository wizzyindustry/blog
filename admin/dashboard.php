<?php
/**
 * Admin Dashboard - Post Management
 * UDOM Student Materials Blog
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Dashboard';

// Fetch statistics
$total_posts_stmt = $pdo->query("SELECT COUNT(*) FROM posts");
$total_posts = (int)$total_posts_stmt->fetchColumn();

$total_comments_stmt = $pdo->query("SELECT COUNT(*) FROM comments");
$total_comments = (int)$total_comments_stmt->fetchColumn();

$total_categories_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$total_categories = (int)$total_categories_stmt->fetchColumn();

// Fetch all posts with their comment counts
$posts_query = "
    SELECT p.*, COUNT(c.id) AS comment_count 
    FROM posts p 
    LEFT JOIN comments c ON p.id = c.post_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
";
$posts = $pdo->query($posts_query)->fetchAll();

// One-time messages set by create / edit / delete
$flash_success = get_flash('success');
$flash_error = get_flash('error');

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container">

    <!-- Flash Alerts -->
    <?php if (!empty($flash_success)): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($flash_error); ?>
      </div>
    <?php endif; ?>

    <!-- Header Actions -->
    <div class="card-header-flex">
      <div>
        <h1><i class="fa-solid fa-table-columns"></i> Content Management</h1>
        <p class="text-muted" style="margin-bottom: 0;">Manage, update, and publish educational materials for UDOM students.</p>
      </div>
      <div>
        <a href="create.php" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i> Create New Post
        </a>
      </div>
    </div>

    <!-- Metrics Stat Cards -->
    <div class="admin-stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fa-solid fa-newspaper"></i>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?php echo $total_posts; ?></div>
          <div class="stat-label">Total Posts</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <i class="fa-solid fa-comments"></i>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?php echo $total_comments; ?></div>
          <div class="stat-label">Total Comments</div>
        </div>
      </div>

      <a href="categories.php" class="stat-card stat-card-link" title="Manage categories">
        <div class="stat-icon">
          <i class="fa-solid fa-folder-tree"></i>
        </div>
        <div class="stat-info">
          <div class="stat-number"><?php echo $total_categories; ?></div>
          <div class="stat-label">Categories <i class="fa-solid fa-arrow-right"></i></div>
        </div>
      </a>
    </div>

    <!-- Posts Data Table -->
    <div class="admin-card">
      <div class="card-header-flex">
        <h2><i class="fa-solid fa-list-check"></i> Published Articles (<?php echo count($posts); ?>)</h2>
        <span class="text-muted">Sorted by newest first</span>
      </div>

      <?php if (!empty($posts)): ?>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 80px;">Thumbnail</th>
                <th>Title</th>
                <th>Category</th>
                <th>Author</th>
                <th>Comments</th>
                <th>Published Date</th>
                <th style="width: 140px; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($posts as $post): ?>
                <?php $thumb_path = post_image_url($post['image'], '../'); ?>
                <tr>
                  <td>
                    <img 
                      src="<?php echo $thumb_path; ?>" 
                      alt="<?php echo sanitize($post['title']); ?>" 
                      class="table-thumb"
                      onerror="this.onerror=null;this.src='../uploads/default-post.svg';"
                    >
                  </td>
                  <td>
                    <div class="table-title">
                      <a href="../post.php?id=<?php echo (int)$post['id']; ?>" target="_blank" style="color: var(--color-primary);">
                        <?php echo sanitize($post['title']); ?>
                      </a>
                    </div>
                  </td>
                  <td>
                    <span class="category-badge" style="position: static; font-size: 11px;">
                      <?php echo sanitize($post['category']); ?>
                    </span>
                  </td>
                  <td>
                    <span class="text-muted"><?php echo sanitize($post['author'] ?: 'UDOM Student Materials'); ?></span>
                  </td>
                  <td>
                    <span class="badge" style="font-weight: 600; color: var(--color-primary);">
                      <i class="fa-solid fa-comment"></i> <?php echo (int)$post['comment_count']; ?>
                    </span>
                  </td>
                  <td>
                    <span class="text-muted"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                  </td>
                  <td style="text-align: right;">
                    <div class="table-actions" style="justify-content: flex-end;">
                      <a 
                        href="edit.php?id=<?php echo (int)$post['id']; ?>" 
                        class="btn btn-outline btn-sm" 
                        title="Edit Article"
                      >
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                      </a>
                      <!-- Delete uses POST + CSRF token so it cannot be triggered by a link -->
                      <form action="delete.php?id=<?php echo (int)$post['id']; ?>" method="POST" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete"
                          title="Delete post" aria-label="Delete post: <?php echo sanitize($post['title']); ?>"
                          data-title="<?php echo sanitize($post['title']); ?>">
                          <i class="fa-solid fa-trash-can"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="fa-solid fa-folder-open"></i></div>
          <h3 class="empty-title">No Articles in Database</h3>
          <p class="empty-desc">You have not published any educational materials yet. Click the button below to add your first article.</p>
          <a href="create.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Create First Post
          </a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

