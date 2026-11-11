<?php
/**
 * My Profile: name, username, email, bio and profile photo.
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'My Profile';
$errors = [];
$data = ['full_name' => $user['full_name'], 'username' => $user['username'], 'email' => $user['email'], 'bio' => (string) $user['bio']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        [$data, $errors] = validate_user_profile($pdo, (int) $user['id']);

        $avatar_error = null;
        $new_avatar = $errors ? null : handle_avatar_upload('avatar', $avatar_error);
        if ($avatar_error) {
            $errors['avatar'] = $avatar_error;
        }

        if (!$errors) {
            $avatar = $user['avatar'];
            if ($new_avatar) {
                $avatar = $new_avatar;
            } elseif (!empty($_POST['remove_avatar'])) {
                $avatar = null;
            }
            try {
                $pdo->prepare(
                    'UPDATE users SET full_name = :n, username = :u, email = :e, bio = :b, avatar = :a WHERE id = :id'
                )->execute([
                    ':n' => $data['full_name'], ':u' => $data['username'], ':e' => $data['email'],
                    ':b' => $data['bio'] !== '' ? $data['bio'] : null, ':a' => $avatar, ':id' => (int) $user['id'],
                ]);
                if ($avatar !== $user['avatar']) {
                    delete_avatar_file($user['avatar']);
                }
                $_SESSION['user_username'] = $data['username'];
                flash('success', 'Your profile was updated.');
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                delete_avatar_file($new_avatar);
                $errors['form'] = $e->getCode() === '23000'
                    ? 'That username or email is already in use.'
                    : 'Your profile could not be saved. Please try again.';
            }
        } else {
            delete_avatar_file($new_avatar);
        }
    }
}

$flash_success = get_flash('success');

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="form-error">' . sanitize($errors[$key]) . '</div>' : '';
}

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
  <?php endif; ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($errors['form']); ?></div>
  <?php elseif ($errors): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Please fix the highlighted fields.</div>
  <?php endif; ?>

  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-user-pen"></i> My Profile</h1>
      <p class="text-muted">Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>.</p>
    </div>
  </div>

  <div class="profile-grid">
    <aside class="admin-card profile-summary">
      <?php echo user_avatar_html($user, '../', 'avatar avatar-xl'); ?>
      <h2><?php echo sanitize($user['full_name']); ?></h2>
      <p class="text-muted">@<?php echo sanitize($user['username']); ?></p>
      <?php if ($user['bio']): ?><p class="profile-bio"><?php echo sanitize($user['bio']); ?></p><?php endif; ?>
      <ul class="profile-facts">
        <li><i class="fa-solid fa-file"></i> <?php echo number_format((int) $user['file_count']); ?> files</li>
        <li><i class="fa-solid fa-hard-drive"></i> <?php echo format_bytes((int) $user['storage_used']); ?> used</li>
        <li><i class="fa-solid fa-right-to-bracket"></i> Last login <?php echo time_ago($user['last_login_at']); ?></li>
      </ul>
    </aside>

    <section class="admin-card">
      <form action="profile.php" method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div class="form-group">
          <label for="avatar" class="form-label"><i class="fa-solid fa-camera"></i> Profile Photo</label>
          <input type="file" name="avatar" id="avatar" class="form-control<?php echo isset($errors['avatar']) ? ' is-invalid' : ''; ?>"
            accept="image/jpeg,image/png,image/webp,image/gif">
          <p class="form-hint">JPG, PNG, WEBP or GIF, up to 2 MB.</p>
          <?php echo field_error($errors, 'avatar'); ?>
          <?php if ($user['avatar']): ?>
            <label class="checkbox-label"><input type="checkbox" name="remove_avatar" value="1"> <span>Remove current photo</span></label>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="full_name" class="form-label"><i class="fa-solid fa-id-card"></i> Full Name</label>
          <input type="text" name="full_name" id="full_name" class="form-control<?php echo isset($errors['full_name']) ? ' is-invalid' : ''; ?>"
            value="<?php echo sanitize($data['full_name']); ?>" required minlength="3" maxlength="120">
          <?php echo field_error($errors, 'full_name'); ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="username" class="form-label"><i class="fa-solid fa-at"></i> Username</label>
            <input type="text" name="username" id="username" class="form-control<?php echo isset($errors['username']) ? ' is-invalid' : ''; ?>"
              value="<?php echo sanitize($data['username']); ?>" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}">
            <?php echo field_error($errors, 'username'); ?>
          </div>
          <div class="form-group">
            <label for="email" class="form-label"><i class="fa-solid fa-envelope"></i> Email</label>
            <input type="email" name="email" id="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
              value="<?php echo sanitize($data['email']); ?>" required maxlength="190">
            <?php echo field_error($errors, 'email'); ?>
          </div>
        </div>

        <div class="form-group">
          <label for="bio" class="form-label"><i class="fa-solid fa-align-left"></i> Bio</label>
          <textarea name="bio" id="bio" class="form-control" rows="3" maxlength="300"
            placeholder="e.g. BSc Computer Science, Year 2"><?php echo sanitize($data['bio']); ?></textarea>
          <?php echo field_error($errors, 'bio'); ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </div>
      </form>
    </section>
  </div>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
