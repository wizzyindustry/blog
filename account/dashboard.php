<?php
/**
 * User Dashboard: storage, file breakdown and recent uploads.
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'Dashboard';

// File counts and sizes per type (uses index user_id + file_kind)
$stmt = $pdo->prepare(
    'SELECT file_kind, COUNT(*) AS n, COALESCE(SUM(file_size), 0) AS bytes, COALESCE(SUM(download_count), 0) AS downloads
     FROM user_files WHERE user_id = :uid GROUP BY file_kind'
);
$stmt->execute([':uid' => (int) $user['id']]);
$by_kind = [];
$total_downloads = 0;
foreach ($stmt->fetchAll() as $row) {
    $by_kind[$row['file_kind']] = $row;
    $total_downloads += (int) $row['downloads'];
}

$public_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_files WHERE user_id = :uid AND visibility = 'public'");
$public_stmt->execute([':uid' => (int) $user['id']]);
$public_count = (int) $public_stmt->fetchColumn();

$recent = $pdo->prepare('SELECT * FROM user_files WHERE user_id = :uid ORDER BY created_at DESC, id DESC LIMIT 6');
$recent->execute([':uid' => (int) $user['id']]);
$recent_files = $recent->fetchAll();

$storage_pct = min(100, round(((int) $user['storage_used'] / USER_STORAGE_QUOTA) * 100, 1));
$flash_success = get_flash('success');

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
  <?php endif; ?>

  <div class="page-heading">
    <div>
      <h1>Welcome back, <?php echo sanitize(explode(' ', $user['full_name'])[0]); ?></h1>
      <p class="text-muted">Here is an overview of your uploads and storage.</p>
    </div>
    <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</a>
  </div>

  <div class="kpi-grid">
    <div class="stat-card">
      <div class="stat-icon"><i class="fa-solid fa-file"></i></div>
      <div class="stat-info">
        <div class="stat-number"><?php echo number_format((int) $user['file_count']); ?></div>
        <div class="stat-label">Files Uploaded</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa-solid fa-images"></i></div>
      <div class="stat-info">
        <div class="stat-number"><?php echo number_format((int) ($by_kind['image']['n'] ?? 0)); ?></div>
        <div class="stat-label">Images</div>
      </div>
    </div>
    <a href="files.php?visibility=public" class="stat-card stat-card-link">
      <div class="stat-icon"><i class="fa-solid fa-earth-africa"></i></div>
      <div class="stat-info">
        <div class="stat-number"><?php echo number_format($public_count); ?></div>
        <div class="stat-label">Shared Publicly</div>
      </div>
    </a>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa-solid fa-hard-drive"></i></div>
      <div class="stat-info">
        <div class="stat-number"><?php echo format_bytes((int) $user['storage_used']); ?></div>
        <div class="stat-label">Storage Used (<?php echo $storage_pct; ?>%)</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa-solid fa-download"></i></div>
      <div class="stat-info">
        <div class="stat-number"><?php echo number_format($total_downloads); ?></div>
        <div class="stat-label">Downloads</div>
      </div>
    </div>
  </div>

  <div class="dash-grid">
    <section class="admin-card">
      <div class="card-header-flex">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Uploads</h2>
        <a href="files.php" class="btn btn-outline btn-sm">View all</a>
      </div>
      <?php if ($recent_files): ?>
        <ul class="file-list">
          <?php foreach ($recent_files as $file): ?>
            <li class="file-list-item">
              <?php if (user_file_is_image($file)): ?>
                <img class="file-thumb" src="file.php?id=<?php echo (int) $file['id']; ?>&amp;view=1" alt="" loading="lazy">
              <?php else: ?>
                <span class="file-thumb file-thumb-icon kind-<?php echo sanitize($file['file_kind']); ?>">
                  <i class="fa-solid <?php echo user_file_icon($file); ?>"></i>
                </span>
              <?php endif; ?>
              <div class="file-list-info">
                <a href="file.php?id=<?php echo (int) $file['id']; ?><?php echo user_file_inline($file) ? '&amp;view=1' : ''; ?>"
                  class="file-name" <?php echo user_file_inline($file) ? 'target="_blank" rel="noopener"' : ''; ?>>
                  <?php echo sanitize(user_file_title($file)); ?>
                </a>
                <span class="text-muted">
                  <?php echo $file['visibility'] === 'public' ? '<i class="fa-solid fa-earth-africa"></i> Public' : '<i class="fa-solid fa-lock"></i> Private'; ?>
                  &middot; <?php echo format_bytes((int) $file['file_size']); ?> &middot; <?php echo time_ago($file['created_at']); ?>
                </span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state empty-state-sm">
          <div class="empty-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
          <h3 class="empty-title">No files yet</h3>
          <p class="empty-desc">Upload notes, images, videos, past papers or any other file.</p>
          <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Upload your first file</a>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <div class="card-header-flex">
        <h2><i class="fa-solid fa-chart-pie"></i> Storage by Type</h2>
      </div>
      <ul class="kind-bars">
        <?php foreach (FILE_KINDS as $kind => [$label, $icon]): ?>
          <?php
          $bytes = (int) ($by_kind[$kind]['bytes'] ?? 0);
          $pct = $user['storage_used'] > 0 ? round($bytes / (int) $user['storage_used'] * 100) : 0;
          ?>
          <li>
            <div class="kind-bar-label">
              <a href="files.php?kind=<?php echo $kind; ?>"><i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $label; ?></a>
              <span class="text-muted"><?php echo number_format((int) ($by_kind[$kind]['n'] ?? 0)); ?> &middot; <?php echo format_bytes($bytes); ?></span>
            </div>
            <div class="progress"><div class="progress-bar" style="width: <?php echo $pct; ?>%;"></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="text-muted kind-bars-note">
        <?php echo format_bytes(max(0, USER_STORAGE_QUOTA - (int) $user['storage_used'])); ?> free of <?php echo format_bytes(USER_STORAGE_QUOTA); ?>.
      </p>
    </section>
  </div>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
