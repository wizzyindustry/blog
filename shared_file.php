<?php
/**
 * One shared (public) file: preview in the browser when possible, details, download.
 * Previews: images, PDF, video, audio and plain text. Other types: download only.
 */

require_once __DIR__ . '/config/db.php';

$stmt = $pdo->prepare(
    "SELECT f.*, u.full_name, u.username, u.avatar, u.bio
     FROM user_files f JOIN users u ON u.id = f.user_id
     WHERE f.id = :id AND f.visibility = 'public' AND u.status = 'active' LIMIT 1"
);
$stmt->execute([':id' => (int) input_text($_GET, 'id', '0')]);
$file = $stmt->fetch();

if (!$file) {
    render_error_page(404, 'File not available', 'This file does not exist or its owner made it private.');
}

$id = (int) $file['id'];
$title = user_file_title($file);
$src = 'account/file.php?id=' . $id . '&view=1';
$mime = INLINE_MIME_TYPES[$file['extension']] ?? '';
$type = upload_type_for_extension((string) $file['extension']);

// More public files from the same student
$more = $pdo->prepare(
    "SELECT f.*, u.full_name, u.username, u.avatar FROM user_files f JOIN users u ON u.id = f.user_id
     WHERE f.user_id = :uid AND f.visibility = 'public' AND f.id <> :id ORDER BY f.created_at DESC LIMIT 4"
);
$more->execute([':uid' => (int) $file['user_id'], ':id' => $id]);
$more_files = $more->fetchAll();

$active_nav = 'shared';
$page_title = $title;
$meta_description = $file['description'] ?: strtoupper($file['extension']) . ' file shared by ' . $file['full_name'] . ' on UDOM Student Materials.';
$meta_canonical = 'shared_file.php?id=' . $id;
if (user_file_is_image($file)) {
    $meta_image = 'account/file.php?id=' . $id . '&view=1';
}
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
  <div class="container">
    <a href="shared.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> All shared files</a>

    <div class="shared-detail">
      <section class="shared-preview">
        <?php if (strpos($mime, 'image/') === 0): ?>
          <img src="<?php echo sanitize($src); ?>" alt="<?php echo sanitize($title); ?>">
        <?php elseif ($mime === 'application/pdf'): ?>
          <iframe src="<?php echo sanitize($src); ?>" title="<?php echo sanitize($title); ?>" loading="lazy"></iframe>
        <?php elseif (strpos($mime, 'video/') === 0): ?>
          <video src="<?php echo sanitize($src); ?>" controls preload="metadata"></video>
        <?php elseif (strpos($mime, 'audio/') === 0): ?>
          <div class="shared-preview-icon"><i class="fa-solid fa-file-audio"></i></div>
          <audio src="<?php echo sanitize($src); ?>" controls preload="metadata"></audio>
        <?php elseif (strpos($mime, 'text/') === 0): ?>
          <iframe src="<?php echo sanitize($src); ?>" title="<?php echo sanitize($title); ?>" sandbox loading="lazy" class="text-preview"></iframe>
        <?php else: ?>
          <div class="shared-preview-icon type-<?php echo $type; ?>">
            <i class="fa-solid <?php echo user_file_icon($file); ?>"></i>
            <p>Preview is not available for <?php echo sanitize(strtoupper($file['extension'] ?: 'this')); ?> files.<br>Download it to open on your device.</p>
          </div>
        <?php endif; ?>
      </section>

      <aside class="admin-card shared-info">
        <span class="kind-pill"><?php echo sanitize(strtoupper($file['extension'] ?: file_kind_label($file['file_kind']))); ?></span>
        <h1><?php echo sanitize($title); ?></h1>
        <?php if ($file['description']): ?><p><?php echo sanitize($file['description']); ?></p><?php endif; ?>

        <div class="shared-owner">
          <?php echo user_avatar_html($file, '', 'avatar avatar-md'); ?>
          <div>
            <strong><?php echo sanitize($file['full_name']); ?></strong>
            <span class="text-muted">@<?php echo sanitize($file['username']); ?></span>
          </div>
        </div>

        <ul class="profile-facts">
          <li><i class="fa-solid fa-file"></i> <?php echo sanitize($file['original_name']); ?></li>
          <li><i class="fa-solid fa-weight-hanging"></i> <?php echo format_bytes((int) $file['file_size']); ?></li>
          <li><i class="fa-regular fa-calendar"></i> Shared <?php echo date('M d, Y', strtotime($file['created_at'])); ?></li>
          <li><i class="fa-solid fa-download"></i> <?php echo number_format((int) $file['download_count']); ?> downloads</li>
          <?php if ($file['category']): ?>
            <li><i class="fa-solid <?php echo category_icon($file['category']); ?>"></i>
              <a href="shared.php?category=<?php echo rawurlencode($file['category']); ?>"><?php echo sanitize($file['category']); ?></a></li>
          <?php endif; ?>
        </ul>

        <div class="shared-info-actions">
          <a href="account/file.php?id=<?php echo $id; ?>" class="btn btn-primary btn-block" data-no-loader><i class="fa-solid fa-download"></i> Download</a>
          <?php if ($mime !== ''): ?>
            <a href="<?php echo sanitize($src); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-block"><i class="fa-solid fa-up-right-from-square"></i> Open in new tab</a>
          <?php endif; ?>
        </div>
      </aside>
    </div>

    <?php if ($more_files): ?>
      <section class="shared-section">
        <div class="section-header">
          <h2 class="section-title"><i class="fa-solid fa-user"></i> More from <?php echo sanitize($file['full_name']); ?></h2>
        </div>
        <div class="shared-grid"><?php echo render_shared_grid($more_files); ?></div>
      </section>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
