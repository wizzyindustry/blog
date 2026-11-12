<?php
/**
 * Admin: change password.
 * Required right after login while the account still uses the default password.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Change Password';
$errors = [];
$must_change = !empty($_SESSION['admin_must_change']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = input_text($_POST, 'current_password');
    $new = input_text($_POST, 'new_password');
    $confirm = input_text($_POST, 'new_password_confirm');
    $who = 'a:' . (int) $_SESSION['admin_id'];

    $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = :id');
    $stmt->execute([':id' => (int) $_SESSION['admin_id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (rate_limited('password', $who, 5, 15)) {
        $errors[] = 'Too many wrong passwords. Please wait 15 minutes and try again.';
    } elseif (!password_verify($current, $hash)) {
        rate_hit('password', $who);
        $errors[] = 'Your current password is incorrect.';
    } elseif (strlen($new) < 12 || strlen($new) > 255) {
        $errors[] = 'The new admin password must be at least 12 characters.';
    } elseif (!preg_match('/[a-z]/', $new) || !preg_match('/[A-Z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
        $errors[] = 'Use upper and lower case letters, a number and a symbol.';
    } elseif (in_array(strtolower($new), ['admin123', 'password123!', 'administrator1!'], true) || stripos($new, 'admin') !== false) {
        $errors[] = 'Choose a password that does not contain the word "admin".';
    } elseif (!hash_equals($new, $confirm)) {
        $errors[] = 'The two new passwords do not match.';
    } elseif (password_verify($new, $hash)) {
        $errors[] = 'The new password must be different from the current one.';
    } else {
        $pdo->prepare('UPDATE admins SET password = :p WHERE id = :id')
            ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int) $_SESSION['admin_id']]);
        unset($_SESSION['admin_must_change']);
        session_regenerate_id(true);
        flash('success', 'Your admin password was changed.');
        header('Location: dashboard.php');
        exit;
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container" style="max-width: 640px;">
    <div class="page-heading">
      <div>
        <h1><i class="fa-solid fa-key"></i> Change Password</h1>
        <p class="text-muted">At least 12 characters with upper and lower case letters, a number and a symbol.</p>
      </div>
    </div>

    <?php if ($must_change): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-shield-halved"></i>
        <div>This account still uses the <strong>default password</strong>, which is published in the project's README.
          You must choose a new password before you can use the admin panel.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($error); ?></div>
    <?php endforeach; ?>

    <section class="admin-card">
      <form action="password.php" method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label for="current_password" class="form-label">Current Password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" required maxlength="255" autocomplete="current-password" autofocus>
        </div>
        <div class="form-group">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" name="new_password" id="new_password" class="form-control" required minlength="12" maxlength="255" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="new_password_confirm" class="form-label">Confirm New Password</label>
          <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-control" required minlength="12" maxlength="255" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><i class="fa-solid fa-floppy-disk"></i> Change Password</button>
      </form>
    </section>
  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
