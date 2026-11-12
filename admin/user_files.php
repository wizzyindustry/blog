<?php
/**
 * Admin: every file uploaded by users (search, type filter, pagination, delete).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'User Uploads';

$search = trim(input_text($_GET, 'q'));
$kind = input_text($_GET, 'kind');
$kind = isset(FILE_KINDS[$kind]) ? $kind : '';
$sort = input_text($_GET, 'sort') === 'largest' ? 'largest' : 'newest';
$visibility = input_text($_GET, 'visibility');
$visibility = in_array($visibility, ['public', 'private'], true) ? $visibility : '';

$where = [];
$params = [];
if ($kind !== '') {
    $where[] = 'f.file_kind = :kind';
    $params[':kind'] = $kind;
}
if ($visibility !== '') {
    $where[] = 'f.visibility = :vis';
    $params[':vis'] = $visibility;
}
if ($search !== '') {
    $where[] = 'f.original_name LIKE :q';
    $params[':q'] = '%' . addcslashes($search, '%_\\') . '%';
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$count = $pdo->prepare('SELECT COUNT(*) FROM user_files f' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / ADMIN_ROWS_PER_PAGE));
$page = min(current_page_number(), $total_pages);

$stmt = $pdo->prepare(
    'SELECT f.*, u.full_name, u.username FROM user_files f JOIN users u ON u.id = f.user_id' . $where_sql
    . ' ORDER BY ' . ($sort === 'largest' ? 'f.file_size DESC' : 'f.created_at DESC, f.id DESC')
    . ' LIMIT :limit OFFSET :offset'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$files = $stmt->fetchAll();

$flash_success = get_flash('success');
$flash_error = get_flash('error');

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container">
    <?php if ($flash_success !== ''): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
    <?php endif; ?>
    <?php if ($flash_error !== ''): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($flash_error); ?></div>
    <?php endif; ?>

    <div class="page-heading">
      <div>
        <h1><i class="fa-solid fa-cloud-arrow-up"></i> User Uploads</h1>
        <p class="text-muted"><?php echo number_format($total); ?> file<?php echo $total === 1 ? '' : 's'; ?><?php echo $where ? ' match your filters' : ''; ?>.</p>
      </div>
    </div>

    <form class="toolbar" method="GET" action="user_files.php">
      <div class="toolbar-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" class="form-control" placeholder="Search file names" value="<?php echo sanitize($search); ?>" maxlength="100">
      </div>
      <select name="kind" class="form-control" aria-label="File type" data-autosubmit>
        <option value="">All types</option>
        <?php foreach (FILE_KINDS as $k => [$label]): ?>
          <option value="<?php echo $k; ?>" <?php echo $kind === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <select name="visibility" class="form-control" aria-label="Visibility" data-autosubmit>
        <option value="">Public &amp; private</option>
        <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Public (on Materials page)</option>
        <option value="private" <?php echo $visibility === 'private' ? 'selected' : ''; ?>>Private</option>
      </select>
      <select name="sort" class="form-control" aria-label="Sort" data-autosubmit>
        <option value="newest">Newest first</option>
        <option value="largest" <?php echo $sort === 'largest' ? 'selected' : ''; ?>>Largest first</option>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
      <?php if ($where || $sort !== 'newest'): ?><a href="user_files.php" class="btn btn-ghost">Reset</a><?php endif; ?>
    </form>

    <?php if ($files): ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th></th><th>File</th><th>Owner</th><th>Type</th><th>Visibility</th><th>Size</th><th>Uploaded</th><th style="text-align:right;">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file): ?>
              <?php $fid = (int) $file['id']; ?>
              <tr>
                <td>
                  <?php if (user_file_is_image($file)): ?>
                    <img class="file-thumb" src="../account/file.php?id=<?php echo $fid; ?>&amp;view=1" alt="" loading="lazy">
                  <?php else: ?>
                    <span class="file-thumb file-thumb-icon kind-<?php echo sanitize($file['file_kind']); ?>"><i class="fa-solid <?php echo user_file_icon($file); ?>"></i></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="table-title file-name-cell">
                    <?php if ($file['visibility'] === 'public'): ?>
                      <a href="../shared_file.php?id=<?php echo $fid; ?>" target="_blank" rel="noopener"><?php echo sanitize(user_file_title($file)); ?></a>
                    <?php else: ?>
                      <?php echo sanitize(user_file_title($file)); ?>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted table-sub file-name-cell"><?php echo sanitize($file['original_name']); ?><?php echo $file['category'] ? ' &middot; ' . sanitize($file['category']) : ''; ?></div>
                  <?php if ($file['description']): ?><div class="text-muted table-sub"><?php echo sanitize($file['description']); ?></div><?php endif; ?>
                </td>
                <td><a href="user.php?id=<?php echo (int) $file['user_id']; ?>"><?php echo sanitize($file['full_name']); ?></a></td>
                <td><span class="kind-pill kind-<?php echo sanitize($file['file_kind']); ?>"><?php echo sanitize(strtoupper($file['extension'] ?: file_kind_label($file['file_kind']))); ?></span></td>
                <td>
                  <?php if ($file['visibility'] === 'public'): ?>
                    <span class="status-pill status-online"><i class="fa-solid fa-earth-africa"></i> Public</span>
                  <?php else: ?>
                    <span class="status-pill"><i class="fa-solid fa-lock"></i> Private</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?php echo format_bytes((int) $file['file_size']); ?></td>
                <td class="text-muted"><?php echo date('M d, Y H:i', strtotime($file['created_at'])); ?></td>
                <td>
                  <div class="table-actions" style="justify-content: flex-end;">
                    <a href="../account/file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm" title="Download" data-no-loader><i class="fa-solid fa-download"></i></a>
                    <form action="user_action.php" method="POST" class="inline-form">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo $fid; ?>">
                      <input type="hidden" name="return" value="<?php echo sanitize('user_files.php' . query_with([])); ?>">
                      <?php if ($file['visibility'] === 'public'): ?>
                        <button type="submit" name="action" value="hide_file" class="btn btn-outline btn-sm" title="Remove from the Materials page (make private)"
                          data-confirm="Make this file private? It will disappear from the Materials page." data-loading-text=""><i class="fa-solid fa-eye-slash"></i></button>
                      <?php endif; ?>
                      <button type="submit" name="action" value="delete_file" class="btn btn-danger btn-sm" title="Delete"
                        data-confirm="Delete &quot;<?php echo sanitize($file['original_name']); ?>&quot;?" data-loading-text=""><i class="fa-solid fa-trash-can"></i></button>
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
      <div class="admin-card empty-state">
        <div class="empty-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <h3 class="empty-title"><?php echo $where ? 'No files match your filters' : 'No user uploads yet'; ?></h3>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
