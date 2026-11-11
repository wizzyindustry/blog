<?php
/**
 * Admin: one user's profile, activity, sign-ins and files.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => (int) input_text($_GET, 'id', '0')]);
$u = $stmt->fetch();
if (!$u) {
    flash('error', 'That user was not found.');
    header('Location: users.php');
    exit;
}
$uid = (int) $u['id'];
$page_title = $u['full_name'];

$per_page = 15;
$total = (int) $u['file_count'];
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min(current_page_number(), $total_pages);
$files = $pdo->prepare('SELECT * FROM user_files WHERE user_id = :uid ORDER BY created_at DESC, id DESC LIMIT :l OFFSET :o');
$files->bindValue(':uid', $uid, PDO::PARAM_INT);
$files->bindValue(':l', $per_page, PDO::PARAM_INT);
$files->bindValue(':o', ($page - 1) * $per_page, PDO::PARAM_INT);
$files->execute();
$files = $files->fetchAll();

$logins = $pdo->prepare('SELECT ip_address, user_agent, created_at FROM user_logins WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10');
$logins->execute([':uid' => $uid]);
$logins = $logins->fetchAll();

$login_count = $pdo->prepare('SELECT COUNT(*) FROM user_logins WHERE user_id = :uid');
$login_count->execute([':uid' => $uid]);
$login_count = (int) $login_count->fetchColumn();

$flash_success = get_flash('success');
$flash_error = get_flash('error');
$self = 'user.php' . query_with([]);

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
        <a href="users.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> All users</a>
        <h1><?php echo sanitize($u['full_name']); ?></h1>
      </div>
      <form action="user_action.php" method="POST" class="table-actions">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $uid; ?>">
        <input type="hidden" name="return" value="<?php echo sanitize($self); ?>">
        <?php if ($u['status'] === 'blocked'): ?>
          <button type="submit" name="action" value="unblock" class="btn btn-outline" data-loading-text="Unblocking..."><i class="fa-solid fa-unlock"></i> Unblock</button>
        <?php else: ?>
          <button type="submit" name="action" value="block" class="btn btn-outline" data-loading-text="Blocking..."
            data-confirm="Block this user? They will be signed out and unable to log in."><i class="fa-solid fa-ban"></i> Block</button>
        <?php endif; ?>
        <button type="submit" name="action" value="delete_user" class="btn btn-danger" data-loading-text="Deleting..."
          data-confirm="Delete this user and all <?php echo $total; ?> of their files? This cannot be undone."><i class="fa-solid fa-trash-can"></i> Delete</button>
      </form>
    </div>

    <div class="profile-grid">
      <aside class="admin-card profile-summary">
        <?php echo user_avatar_html($u, '../', 'avatar avatar-xl'); ?>
        <h2><?php echo sanitize($u['full_name']); ?></h2>
        <p class="text-muted">@<?php echo sanitize($u['username']); ?></p>
        <?php if ($u['status'] === 'blocked'): ?>
          <span class="status-pill status-blocked"><i class="fa-solid fa-ban"></i> Blocked</span>
        <?php elseif (user_is_online($u['last_seen_at'])): ?>
          <span class="status-pill status-online"><span class="live-dot"></span> Online now</span>
        <?php else: ?>
          <span class="status-pill"><i class="fa-solid fa-circle-check"></i> Active account</span>
        <?php endif; ?>
        <?php if ($u['bio']): ?><p class="profile-bio"><?php echo sanitize($u['bio']); ?></p><?php endif; ?>
        <ul class="profile-facts">
          <li><i class="fa-solid fa-envelope"></i> <?php echo sanitize($u['email']); ?></li>
          <li><i class="fa-solid fa-calendar"></i> Joined <?php echo date('M d, Y', strtotime($u['created_at'])); ?></li>
          <li><i class="fa-solid fa-signal"></i> Last active <?php echo time_ago($u['last_seen_at']); ?></li>
          <li><i class="fa-solid fa-right-to-bracket"></i> <?php echo number_format($login_count); ?> sign-ins</li>
          <li><i class="fa-solid fa-file"></i> <?php echo number_format($total); ?> files &middot; <?php echo format_bytes((int) $u['storage_used']); ?></li>
        </ul>
      </aside>

      <div>
        <section class="admin-card">
          <h2 class="admin-card-title"><i class="fa-solid fa-folder-open"></i> Files (<?php echo number_format($total); ?>)</h2>
          <?php if ($files): ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead><tr><th></th><th>Name</th><th>Size</th><th>Uploaded</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($files as $file): ?>
                    <?php $fid = (int) $file['id']; ?>
                    <tr>
                      <td>
                        <?php if (user_file_is_image($file)): ?>
                          <img class="file-thumb" src="../account/file.php?id=<?php echo $fid; ?>&amp;view=1" alt="" loading="lazy">
                        <?php else: ?>
                          <span class="file-thumb file-thumb-icon kind-<?php echo sanitize($file['file_kind']); ?>"><i class="fa-solid <?php echo file_kind_icon($file['file_kind']); ?>"></i></span>
                        <?php endif; ?>
                      </td>
                      <td class="file-name-cell">
                        <?php echo sanitize(user_file_title($file)); ?>
                        <?php if ($file['visibility'] === 'public'): ?><span class="status-pill status-online"><i class="fa-solid fa-earth-africa"></i> Public</span><?php endif; ?>
                      </td>
                      <td class="text-muted"><?php echo format_bytes((int) $file['file_size']); ?></td>
                      <td class="text-muted"><?php echo date('M d, Y', strtotime($file['created_at'])); ?></td>
                      <td>
                        <div class="table-actions" style="justify-content: flex-end;">
                          <a href="../account/file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm" title="Download" data-no-loader><i class="fa-solid fa-download"></i></a>
                          <form action="user_action.php" method="POST" class="inline-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $fid; ?>">
                            <input type="hidden" name="return" value="<?php echo sanitize($self); ?>">
                            <button type="submit" name="action" value="delete_file" class="btn btn-danger btn-sm" title="Delete file"
                              data-confirm="Delete this file?" data-loading-text=""><i class="fa-solid fa-trash-can"></i></button>
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
            <p class="text-muted">This user has not uploaded any files.</p>
          <?php endif; ?>
        </section>

        <section class="admin-card" style="margin-top: 24px;">
          <h2 class="admin-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent sign-ins</h2>
          <?php if ($logins): ?>
            <ul class="activity-list">
              <?php foreach ($logins as $login): ?>
                <li>
                  <i class="fa-solid fa-right-to-bracket"></i>
                  <div>
                    <strong><?php echo date('M d, Y H:i', strtotime($login['created_at'])); ?></strong>
                    <span class="text-muted"><?php echo sanitize($login['ip_address']); ?> &middot; <?php echo sanitize(mb_substr((string) $login['user_agent'], 0, 90)); ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted">No sign-ins recorded.</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
