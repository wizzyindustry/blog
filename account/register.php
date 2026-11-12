<?php
/**
 * User Registration
 * Creates a student account and logs the new user in.
 */
require_once __DIR__ . '/../config/db.php';

start_session();
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$data = ['full_name' => '', 'username' => '', 'email' => '', 'bio' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif (rate_limited('register', client_ip(), 5, 60)) {
        $errors['form'] = 'Too many accounts were created from your network. Please try again in an hour.';
    } elseif (input_text($_POST, 'website') !== '') {
        // Hidden "website" field: only bots fill it in
        $errors['form'] = 'Registration failed. Please try again.';
    } else {
        [$data, $errors] = validate_user_profile($pdo);
        $password = input_text($_POST, 'password');
        $problem = password_problem($password, input_text($_POST, 'password_confirm'));
        if ($problem !== null) {
            $errors['password'] = $problem;
        }
        // The Terms checkbox is required: checked again here, not only in the browser
        if (input_text($_POST, 'agree') !== '1') {
            $errors['agree'] = 'You must accept the Terms & Conditions and Privacy Policy to create an account.';
        }

        if (!$errors) {
            try {
                // Record WHEN the terms were accepted and WHICH version (proof of consent)
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, username, email, password, terms_accepted_at, terms_version)
                     VALUES (:n, :u, :e, :p, NOW(), :tv)'
                );
                $stmt->execute([
                    ':n' => $data['full_name'],
                    ':u' => $data['username'],
                    ':e' => $data['email'],
                    ':p' => password_hash($password, PASSWORD_DEFAULT),
                    ':tv' => LEGAL_VERSION,
                ]);
                $new_id = (int) $pdo->lastInsertId();
                rate_hit('register', client_ip());
                log_in_user($pdo, ['id' => $new_id, 'username' => $data['username']]);
                flash('success', 'Welcome, ' . $data['full_name'] . '! Your account is ready.');
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                // Two people registering the same name at the same moment
                $errors['form'] = $e->getCode() === '23000'
                    ? 'That username or email was just taken. Please choose another.'
                    : 'Registration is temporarily unavailable. Please try again.';
            }
        }
    }
}

/** Inline error under a field. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="form-error">' . sanitize($errors[$key]) . '</div>' : '';
}

$auth_title = 'Create Account';
$auth_subtitle = 'Join UDOM Student Materials to upload and manage your files';
$auth_wide = true;
require __DIR__ . '/includes/auth_head.php';
?>

<?php if (!empty($errors['form'])): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errors['form']); ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> Please fix the highlighted fields.</div>
<?php endif; ?>

<form action="register.php" method="POST">
  <?php echo csrf_field(); ?>
  <div class="hp-field" aria-hidden="true">
    <label for="website">Website</label>
    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
  </div>

  <div class="form-group">
    <label for="full_name" class="form-label"><i class="fa-solid fa-id-card"></i> Full Name</label>
    <input type="text" name="full_name" id="full_name" class="form-control<?php echo isset($errors['full_name']) ? ' is-invalid' : ''; ?>"
      value="<?php echo sanitize($data['full_name']); ?>" required minlength="3" maxlength="120" autocomplete="name" autofocus>
    <?php echo field_error($errors, 'full_name'); ?>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="username" class="form-label"><i class="fa-solid fa-at"></i> Username</label>
      <input type="text" name="username" id="username" class="form-control<?php echo isset($errors['username']) ? ' is-invalid' : ''; ?>"
        value="<?php echo sanitize($data['username']); ?>" required minlength="3" maxlength="50"
        pattern="[A-Za-z0-9_.\-]{3,50}" title="Letters, numbers, dot, underscore or hyphen (3-50 characters)" autocomplete="username">
      <?php echo field_error($errors, 'username'); ?>
    </div>
    <div class="form-group">
      <label for="email" class="form-label"><i class="fa-solid fa-envelope"></i> Email</label>
      <input type="email" name="email" id="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
        value="<?php echo sanitize($data['email']); ?>" required maxlength="190" autocomplete="email">
      <?php echo field_error($errors, 'email'); ?>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="password" class="form-label"><i class="fa-solid fa-key"></i> Password</label>
      <input type="password" name="password" id="password" class="form-control<?php echo isset($errors['password']) ? ' is-invalid' : ''; ?>"
        required minlength="8" maxlength="255" autocomplete="new-password">
      <?php echo field_error($errors, 'password'); ?>
    </div>
    <div class="form-group">
      <label for="password_confirm" class="form-label"><i class="fa-solid fa-key"></i> Confirm Password</label>
      <input type="password" name="password_confirm" id="password_confirm" class="form-control"
        required minlength="8" maxlength="255" autocomplete="new-password">
    </div>
  </div>
  <p class="form-hint">At least 8 characters, with at least one letter and one number.</p>

  <div class="form-group terms-check<?php echo isset($errors['agree']) ? ' is-invalid' : ''; ?>">
    <label class="checkbox-label">
      <input type="checkbox" name="agree" value="1" required
        <?php echo input_text($_POST, 'agree') === '1' ? 'checked' : ''; ?>
        <?php echo isset($errors['agree']) ? 'aria-invalid="true"' : ''; ?>>
      <span>
        I have read and accept the <a href="../terms.php" target="_blank" rel="noopener">Terms &amp; Conditions</a>
        and the <a href="../privacy.php" target="_blank" rel="noopener">Privacy Policy</a>, and I will only upload
        files I have the right to share.
      </span>
    </label>
    <?php echo field_error($errors, 'agree'); ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block" data-loading-text="Creating account...">
    <i class="fa-solid fa-user-plus"></i> Create Account
  </button>
</form>

<p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>

<?php require __DIR__ . '/includes/auth_foot.php'; ?>
