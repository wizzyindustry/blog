<?php
/**
 * Admin: Posts list (search, category filter, pagination).
 * Paginated so the page stays fast with thousands of posts.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Posts';

$search = trim(input_text($_GET, 'q'));
$category = normalize_category(input_text($_GET, 'category'));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'p.title LIKE :q';
    $params[':q'] = '%' . addcslashes($search, '%_\\') . '%';
}
if ($category !== '') {
    $where[] = 'p.category = :category';
    $params[':category'] = $category;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$count = $pdo->prepare('SELECT COUNT(*) FROM posts p' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / ADMIN_ROWS_PER_PAGE));
$page = min(current_page_number(), $total_pages);

// Comment counts via a correlated subquery on the indexed post_id (no GROUP BY over all posts)
$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.category, p.image, p.author, p.created_at,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
     FROM posts p' . $where_sql . '
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

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

    <div class="page-heading">
      <div>
        <h1><i class="fa-solid fa-newspaper"></i> Posts</h1>
        <p class="text-muted">Manage, update, and publish educational materials for UDOM students.</p>
      </div>
      <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create New Post</a>
    </div>

    <!-- Posts Data Table -->
    <div class="admin-card">
      <div class="card-header-flex">
        <h2><i class="fa-solid fa-list-check"></i> Published Articles (<?php echo number_format($total); ?>)</h2>
        <form method="GET" action="posts.php" class="toolbar toolbar-inline">
          <div class="toolbar-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" class="form-control" placeholder="Search titles" value="<?php echo sanitize($search); ?>" maxlength="100">
          </div>
          <select name="category" class="form-control" aria-label="Category" data-autosubmit>
            <option value="">All categories</option>
            <?php foreach (get_categories() as $cat): ?>
              <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $category === $cat['name'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
        </form>
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
                      data-fallback="../uploads/default-post.svg"
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
                        <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete" data-loading-text=""
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
        <?php echo render_simple_pagination($page, $total_pages); ?>
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
