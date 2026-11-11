<?php
/**
 * My Images: grid view of the user's uploaded pictures.
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'My Images';
$per_page = 24;

$count = $pdo->prepare("SELECT COUNT(*) FROM user_files WHERE user_id = :uid AND file_kind = 'image'");
$count->execute([':uid' => (int) $user['id']]);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min(current_page_number(), $total_pages);

$stmt = $pdo->prepare(
    "SELECT * FROM user_files WHERE user_id = :uid AND file_kind = 'image'
     ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':uid', (int) $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$stmt->execute();
$images = $stmt->fetchAll();

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-images"></i> My Images</h1>
      <p class="text-muted"><?php echo number_format($total); ?> image<?php echo $total === 1 ? '' : 's'; ?>.</p>
    </div>
    <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Images</a>
  </div>

  <?php if ($images): ?>
    <div class="gallery-grid">
      <?php foreach ($images as $img): ?>
        <figure class="gallery-item">
          <?php if (user_file_is_image($img)): ?>
            <a href="file.php?id=<?php echo (int) $img['id']; ?>&amp;view=1" target="_blank" rel="noopener">
              <img src="file.php?id=<?php echo (int) $img['id']; ?>&amp;view=1" alt="<?php echo sanitize($img['original_name']); ?>" loading="lazy">
            </a>
          <?php else: ?>
            <a href="file.php?id=<?php echo (int) $img['id']; ?>" class="gallery-noimg" data-no-loader>
              <i class="fa-solid fa-file-image"></i><span><?php echo sanitize(strtoupper($img['extension'])); ?> (download)</span>
            </a>
          <?php endif; ?>
          <figcaption>
            <span class="gallery-name"><?php echo sanitize($img['original_name']); ?></span>
            <span class="text-muted">
              <?php echo format_bytes((int) $img['file_size']); ?>
              <?php if ($img['width']): ?> &middot; <?php echo (int) $img['width']; ?>&times;<?php echo (int) $img['height']; ?><?php endif; ?>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php echo render_simple_pagination($page, $total_pages); ?>
  <?php else: ?>
    <div class="admin-card empty-state">
      <div class="empty-icon"><i class="fa-solid fa-images"></i></div>
      <h3 class="empty-title">No images yet</h3>
      <p class="empty-desc">Upload JPG, PNG, WEBP, GIF or any other picture.</p>
      <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Images</a>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
