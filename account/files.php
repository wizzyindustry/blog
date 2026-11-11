<?php
/**
 * My Files: search, filter by type, sort and paginate the user's uploads.
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'My Files';

$search = trim(input_text($_GET, 'q'));
$kind = input_text($_GET, 'kind');
$kind = isset(FILE_KINDS[$kind]) ? $kind : '';
$sorts = [
    'newest' => ['Newest first', 'created_at DESC, id DESC'],
    'oldest' => ['Oldest first', 'created_at ASC, id ASC'],
    'name' => ['Name (A-Z)', 'original_name ASC'],
    'largest' => ['Largest first', 'file_size DESC'],
];
$sort = input_text($_GET, 'sort', 'newest');
$sort = isset($sorts[$sort]) ? $sort : 'newest';
$visibility = input_text($_GET, 'visibility');
$visibility = in_array($visibility, ['public', 'private'], true) ? $visibility : '';

$where = 'user_id = :uid';
$params = [':uid' => (int) $user['id']];
if ($kind !== '') {
    $where .= ' AND file_kind = :kind';
    $params[':kind'] = $kind;
}
if ($visibility !== '') {
    $where .= ' AND visibility = :vis';
    $params[':vis'] = $visibility;
}
if ($search !== '') {
    $where .= ' AND (original_name LIKE :q OR description LIKE :q2 OR title LIKE :q3)';
    $params[':q'] = $params[':q2'] = $params[':q3'] = '%' . addcslashes($search, '%_\\') . '%';
}

$count = $pdo->prepare('SELECT COUNT(*) FROM user_files WHERE ' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / USER_FILES_PER_PAGE));
$page = min(current_page_number(), $total_pages);

$stmt = $pdo->prepare(
    'SELECT * FROM user_files WHERE ' . $where . ' ORDER BY ' . $sorts[$sort][1] . ' LIMIT :limit OFFSET :offset'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', USER_FILES_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * USER_FILES_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$files = $stmt->fetchAll();

$flash_success = get_flash('success');
$flash_error = get_flash('error');

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
  <?php endif; ?>
  <?php if ($flash_error !== ''): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($flash_error); ?></div>
  <?php endif; ?>

  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-folder-open"></i> My Files</h1>
      <p class="text-muted"><?php echo number_format($total); ?> file<?php echo $total === 1 ? '' : 's'; ?><?php echo $kind || $search || $visibility ? ' match your filters' : ''; ?>.</p>
    </div>
    <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</a>
  </div>

  <form class="toolbar" method="GET" action="files.php">
    <div class="toolbar-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" name="q" class="form-control" placeholder="Search file name or description" value="<?php echo sanitize($search); ?>" maxlength="100">
    </div>
    <select name="kind" class="form-control" aria-label="File type" onchange="this.form.submit()">
      <option value="">All types</option>
      <?php foreach (FILE_KINDS as $k => [$label]): ?>
        <option value="<?php echo $k; ?>" <?php echo $kind === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
    <select name="visibility" class="form-control" aria-label="Visibility" onchange="this.form.submit()">
      <option value="">Public &amp; private</option>
      <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Public (shared)</option>
      <option value="private" <?php echo $visibility === 'private' ? 'selected' : ''; ?>>Private (only me)</option>
    </select>
    <select name="sort" class="form-control" aria-label="Sort" onchange="this.form.submit()">
      <?php foreach ($sorts as $k => [$label]): ?>
        <option value="<?php echo $k; ?>" <?php echo $sort === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
    <?php if ($search !== '' || $kind !== '' || $visibility !== '' || $sort !== 'newest'): ?>
      <a href="files.php" class="btn btn-ghost">Reset</a>
    <?php endif; ?>
  </form>

  <?php if ($files): ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 64px;"></th>
            <th>Name</th>
            <th>Type</th>
            <th>Visibility</th>
            <th>Size</th>
            <th>Uploaded</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $file): ?>
            <?php $fid = (int) $file['id']; $inline = user_file_inline($file); ?>
            <tr>
              <td>
                <?php if (user_file_is_image($file)): ?>
                  <img class="file-thumb" src="file.php?id=<?php echo $fid; ?>&amp;view=1" alt="" loading="lazy">
                <?php else: ?>
                  <span class="file-thumb file-thumb-icon kind-<?php echo sanitize($file['file_kind']); ?>"><i class="fa-solid <?php echo user_file_icon($file); ?>"></i></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="table-title file-name-cell"><?php echo sanitize(user_file_title($file)); ?></div>
                <div class="text-muted table-sub file-name-cell">
                  <?php echo sanitize($file['original_name']); ?>
                  <?php if ($file['category']): ?> &middot; <i class="fa-solid fa-folder"></i> <?php echo sanitize($file['category']); ?><?php endif; ?>
                </div>
                <?php if ($file['description']): ?>
                  <div class="text-muted table-sub"><?php echo sanitize($file['description']); ?></div>
                <?php endif; ?>
              </td>
              <td><span class="kind-pill kind-<?php echo sanitize($file['file_kind']); ?>"><?php echo sanitize(strtoupper($file['extension'] ?: file_kind_label($file['file_kind']))); ?></span></td>
              <td>
                <!-- One click switches between Private and Public -->
                <form action="file_visibility.php" method="POST" class="inline-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo $fid; ?>">
                  <input type="hidden" name="return" value="<?php echo sanitize(query_with([])); ?>">
                  <?php if ($file['visibility'] === 'public'): ?>
                    <button type="submit" name="visibility" value="private" class="visibility-toggle is-public" title="Shared on the Materials page. Click to make private." data-loading-text="">
                      <i class="fa-solid fa-earth-africa"></i> Public
                    </button>
                  <?php else: ?>
                    <button type="submit" name="visibility" value="public" class="visibility-toggle" title="Only you can see this. Click to share publicly." data-loading-text="">
                      <i class="fa-solid fa-lock"></i> Private
                    </button>
                  <?php endif; ?>
                </form>
              </td>
              <td class="text-muted"><?php echo format_bytes((int) $file['file_size']); ?></td>
              <td class="text-muted" title="<?php echo sanitize($file['created_at']); ?>"><?php echo date('M d, Y', strtotime($file['created_at'])); ?></td>
              <td>
                <div class="table-actions" style="justify-content: flex-end;">
                  <?php if ($inline): ?>
                    <a href="file.php?id=<?php echo $fid; ?>&amp;view=1" target="_blank" rel="noopener" class="btn btn-outline btn-sm" title="Open">
                      <i class="fa-solid fa-eye"></i>
                    </a>
                  <?php endif; ?>
                  <a href="edit_file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm" title="Edit details and visibility">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <a href="file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm" title="Download" data-no-loader>
                    <i class="fa-solid fa-download"></i>
                  </a>
                  <form action="delete_file.php" method="POST" class="inline-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $fid; ?>">
                    <input type="hidden" name="return" value="<?php echo sanitize(query_with([])); ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete" aria-label="Delete <?php echo sanitize($file['original_name']); ?>"
                      data-confirm="Delete &quot;<?php echo sanitize($file['original_name']); ?>&quot;? This cannot be undone." data-loading-text="">
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
    <div class="admin-card empty-state">
      <div class="empty-icon"><i class="fa-solid fa-folder-open"></i></div>
      <h3 class="empty-title"><?php echo $search || $kind || $visibility ? 'No files match your filters' : 'No files yet'; ?></h3>
      <p class="empty-desc"><?php echo $search || $kind || $visibility ? 'Try a different search or file type.' : 'Upload your first file to see it here.'; ?></p>
      <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</a>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
