<?php
/**
 * "Finish creating your account" after a first Google sign-in.
 * The verified Google identity waits in the session for up to 10 minutes; the account is only
 * created once the student confirms a name and username and accepts the Terms & Privacy Policy.
 */
require_once __DIR__ . '/../config/db.php';

start_session();
$pending = $_SESSION['google_pending'] ?? null;
if (!is_array($pending) || (int) ($pending['expires'] ?? 0) < time()) {
    unset($_SESSION['google_pending']);
    flash('error', 'Your Google sign-in step expired. Please choose "Continue with Google" again.');
    header('Location: login.php', true, 303);
    exit;
}

$errors = [];
$data = [
    'full_name' => (string) $pending['name'],
    'username' => google_suggest_username($pdo, (string) $pending['email'], (string) $pending['name']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif (rate_limited('register', client_ip(), 5, 60)) {
        $errors['form'] = 'Too many accounts were created from your network. Please try again in an hour.';
    } else {
        // Same validation as normal sign-up. The email is the one Google verified, not a form field.
        $_POST['email'] = (string) $pending['email'];
        $_POST['bio'] = '';
        [$checked, $errors] = validate_user_profile($pdo);
        $data = ['full_name' => $checked['full_name'], 'username' => $checked['username']];
        if (isset($errors['email'])) {
            $errors = ['form' => 'An account with this email address was just created. Sign in with your password, then connect Google under Password & Security.'];
        }
        if (input_text($_POST, 'agree') !== '1') {
            $errors['agree'] = 'You must accept the Terms & Conditions and Privacy Policy to create an account.';
        }

        if (!$errors) {
            try {
                // Google-only account: an unusable random password hash, has_password = 0
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, username, email, password, google_sub, has_password, email_verified_at,
                                        terms_accepted_at, terms_version)
                     VALUES (:n, :u, :e, :p, :g, 0, NOW(), NOW(), :tv)'
                );
                $stmt->execute([
                    ':n' => $checked['full_name'],
                    ':u' => $checked['username'],
                    ':e' => $checked['email'],
                    ':p' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    ':g' => (string) $pending['sub'],
                    ':tv' => LEGAL_VERSION,
                ]);
                $new_id = (int) $pdo->lastInsertId();
                rate_hit('register', client_ip());
                unset($_SESSION['google_pending']);
                log_in_user($pdo, ['id' => $new_id, 'username' => $checked['username']]);
                flash('success', 'Welcome, ' . $checked['full_name'] . '! Your account is ready.');
                header('Location: ' . ((string) $pending['return'] !== '' ? $pending['return'] : 'dashboard.php'), true, 303);
                exit;
            } catch (PDOException $e) {
                $errors['form'] = $e->getCode() === '23000'
                    ? 'That username was just taken, or this Google account already has an account. Please try again.'
                    : 'Registration is temporarily unavailable. Please try again.';
            }
        }
    }
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="form-error">' . sanitize($errors[$key]) . '</div>' : '';
}

$auth_title = 'Finish Creating Your Account';
$auth_subtitle = 'You signed in with Google. Confirm your details to create your account.';
require __DIR__ . '/includes/auth_head.php';
?>

<?php if (!empty($errors['form'])): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($errors['form']); ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> Please fix the highlighted fields.</div>
<?php endif; ?>

<form action="google_finish.php" method="POST">
  <?php echo csrf_field(); ?>

  <div class="form-group">
    <span class="form-label"><i class="fa-solid fa-envelope"></i> Google email</span>
    <p class="google-email"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($pending['email']); ?></p>
  </div>

  <div class="form-group">
    <label for="full_name" class="form-label"><i class="fa-solid fa-id-card"></i> Full Name</label>
    <input type="text" name="full_name" id="full_name" class="form-control<?php echo isset($errors['full_name']) ? ' is-invalid' : ''; ?>"
      value="<?php echo sanitize($data['full_name']); ?>" required minlength="3" maxlength="120" autocomplete="name" autofocus>
    <?php echo field_error($errors, 'full_name'); ?>
  </div>

  <div class="form-group">
    <label for="username" class="form-label"><i class="fa-solid fa-at"></i> Username</label>
    <input type="text" name="username" id="username" class="form-control<?php echo isset($errors['username']) ? ' is-invalid' : ''; ?>"
      value="<?php echo sanitize($data['username']); ?>" required minlength="3" maxlength="50"
      pattern="[A-Za-z0-9_.\-]{3,50}" title="Letters, numbers, dot, underscore or hyphen (3-50 characters)" autocomplete="username">
    <?php echo field_error($errors, 'username'); ?>
    <p class="form-hint">Shown next to your comments and shared files. You can change it later.</p>
  </div>

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

<p class="auth-switch">Not you? <a href="login.php">Use a different account</a></p>

<?php require __DIR__ . '/includes/auth_foot.php'; ?>
