<?php
/**
 * Password & Security: change password, recent sign-ins, delete account.
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'Password & Security';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_text($_POST, 'action');
    $current = input_text($_POST, 'current_password');

    // Reload the hash (current_user() row is cached for the request)
    $hash_stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
    $hash_stmt->execute([':id' => (int) $user['id']]);
    $hash = (string) $hash_stmt->fetchColumn();

    $who = 'u:' . (int) $user['id'];
    if (!in_array($action, ['password', 'delete'], true)) {
        $errors['password'] = 'Unknown action.';
    } elseif (!csrf_valid()) {
        $errors[$action] = 'Your session expired. Please try again.';
    } elseif (rate_limited('password', $who, 5, 15)) {
        $errors[$action] = 'Too many wrong passwords. Please wait 15 minutes and try again.';
    } elseif (!password_verify($current, $hash)) {
        rate_hit('password', $who);
        $errors[$action] = 'Your current password is incorrect.';
    } elseif ($action === 'password') {
        $new = input_text($_POST, 'new_password');
        $problem = password_problem($new, input_text($_POST, 'new_password_confirm'));
        if ($problem !== null) {
            $errors['password'] = $problem;
        } else {
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int) $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
            header('Location: security.php');
            exit;
        }
    } elseif ($action === 'delete') {
        if (input_text($_POST, 'confirm_text') !== 'DELETE') {
            $errors['delete'] = 'Type DELETE in capital letters to confirm.';
        } else {
            delete_user_account($pdo, (int) $user['id']);
            log_out_user();
            flash('success', 'Your account and all your files were deleted.');
            header('Location: login.php');
            exit;
        }
    }
}

$logins = $pdo->prepare('SELECT ip_address, user_agent, created_at FROM user_logins WHERE user_id = :uid ORDER BY created_at DESC LIMIT 8');
$logins->execute([':uid' => (int) $user['id']]);
$recent_logins = $logins->fetchAll();

$flash_success = get_flash('success');

/** Short browser / device label from a user-agent string. */
function device_label(?string $ua): string
{
    $ua = (string) $ua;
    $browser = 'Browser';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $key => $name) {
        if (stripos($ua, $key) !== false) {
            $browser = $name;
            break;
        }
    }
    $os = 'Unknown device';
    foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $key => $name) {
        if (stripos($ua, $key) !== false) {
            $os = $name;
            break;
        }
    }
    return $browser . ' on ' . $os;
}

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
  <?php endif; ?>

  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-shield-halved"></i> Password &amp; Security</h1>
      <p class="text-muted">Keep your account safe.</p>
    </div>
  </div>

  <div class="dash-grid">
    <section class="admin-card">
      <h2 class="admin-card-title"><i class="fa-solid fa-key"></i> Change Password</h2>
      <?php if (!empty($errors['password'])): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($errors['password']); ?></div>
      <?php endif; ?>
      <form action="security.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="password">
        <div class="form-group">
          <label for="current_password" class="form-label">Current Password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" required maxlength="255" autocomplete="current-password">
        </div>
        <div class="form-group">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password">
          <p class="form-hint">At least 8 characters, with at least one letter and one number.</p>
        </div>
        <div class="form-group">
          <label for="new_password_confirm" class="form-label">Confirm New Password</label>
          <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><i class="fa-solid fa-floppy-disk"></i> Update Password</button>
      </form>
    </section>

    <section class="admin-card">
      <h2 class="admin-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Sign-ins</h2>
      <?php if ($recent_logins): ?>
        <ul class="activity-list">
          <?php foreach ($recent_logins as $login): ?>
            <li>
              <i class="fa-solid fa-desktop"></i>
              <div>
                <strong><?php echo sanitize(device_label($login['user_agent'])); ?></strong>
                <span class="text-muted"><?php echo sanitize($login['ip_address']); ?> &middot; <?php echo date('M d, Y H:i', strtotime($login['created_at'])); ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted">No sign-ins recorded yet.</p>
      <?php endif; ?>
    </section>
  </div>

  <section class="admin-card danger-zone">
    <h2 class="admin-card-title text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Delete Account</h2>
    <p class="text-muted">This permanently deletes your account and all <?php echo number_format((int) $user['file_count']); ?> of your files. It cannot be undone.</p>
    <?php if (!empty($errors['delete'])): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($errors['delete']); ?></div>
    <?php endif; ?>
    <form action="security.php" method="POST" class="form-row">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="delete">
      <div class="form-group">
        <label for="delete_password" class="form-label">Current Password</label>
        <input type="password" name="current_password" id="delete_password" class="form-control" required maxlength="255" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="confirm_text" class="form-label">Type DELETE to confirm</label>
        <input type="text" name="confirm_text" id="confirm_text" class="form-control" required pattern="DELETE" autocomplete="off">
      </div>
      <div class="form-group">
        <button type="submit" class="btn btn-danger" data-confirm="Delete your account and all your files permanently?" data-loading-text="Deleting...">
          <i class="fa-solid fa-user-xmark"></i> Delete My Account
        </button>
      </div>
    </form>
  </section>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
