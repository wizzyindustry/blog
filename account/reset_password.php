<?php
/**
 * Choose a new password from an emailed reset link.
 * GET ?token=...  checks the link, keeps it in the session and reloads WITHOUT the token in the
 *                 address (so it is not left in browser history or sent to other sites as a Referer).
 * POST            sets the new password, uses up the link and signs out every other device.
 */
require_once __DIR__ . '/../config/db.php';

start_deferred_output(); // Notification email is sent after the redirect is delivered

start_session();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$invalid_message = 'This reset link is invalid, was already used, or has expired. Reset links work once and expire after '
    . RESET_TOKEN_TTL_MINUTES . ' minutes. Please ask for a new one.';

// 1. Arriving from the email
if (isset($_GET['token'])) {
    $token = input_text($_GET, 'token');
    $ip = client_ip();
    if (rate_limited('resettok', $ip, 20, 15)) {
        too_many_requests('Too many reset attempts from your network. Please wait 15 minutes and try again.');
    }
    $hash = (strlen($token) >= 40 && strlen($token) <= 64) ? reset_token_hash($token) : '';
    if ($hash === '' || !find_password_reset($pdo, $hash)) {
        rate_hit('resettok', $ip);
        unset($_SESSION['reset_token_hash']);
        flash('error', $invalid_message);
        header('Location: forgot_password.php', true, 303);
        exit;
    }
    $_SESSION['reset_token_hash'] = $hash;
    header('Location: reset_password.php', true, 303);
    exit;
}

// 2. The form (the token now lives only in the session)
$reset = find_password_reset($pdo, (string) ($_SESSION['reset_token_hash'] ?? ''));
if (!$reset) {
    unset($_SESSION['reset_token_hash']);
    flash('error', $invalid_message);
    header('Location: forgot_password.php', true, 303);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = input_text($_POST, 'password');
    $problem = password_problem($password, input_text($_POST, 'password_confirm'));
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($problem !== null) {
        $error = $problem;
    } else {
        $pdo->beginTransaction();
        try {
            // Use the link up first: if two tabs submit at once, only one wins
            $used = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL AND expires_at > NOW()');
            $used->execute([':id' => (int) $reset['reset_id']]);
            if ($used->rowCount() !== 1) {
                throw new RuntimeException('link already used');
            }
            $pdo->prepare('UPDATE users SET password = :p, has_password = 1, password_changed_at = NOW() WHERE id = :id')
                ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $reset['id']]);
            // Any other links for this account stop working too
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = :u AND used_at IS NULL')->execute([':u' => (int) $reset['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            unset($_SESSION['reset_token_hash']);
            flash('error', $invalid_message);
            header('Location: forgot_password.php', true, 303);
            exit;
        }

        unset($_SESSION['reset_token_hash']);
        // A successful reset lifts the sign-in lock for this account (by username and by email)
        $pdo->prepare('DELETE FROM login_attempts WHERE scope = \'useracct\' AND ip_address IN (:a, :b)')
            ->execute([':a' => account_key((string) $reset['username']), ':b' => account_key((string) $reset['email'])]);
        $changed_user = $reset;
        defer_after_response(function () use ($changed_user) { send_password_changed_email($changed_user); });
        session_regenerate_id(true);
        flash('success', 'Your password was reset. Sign in with your new password.');
        header('Location: login.php', true, 303);
        exit;
    }
}

$auth_title = 'Choose a New Password';
$auth_subtitle = 'For the account ' . $reset['username'];
require __DIR__ . '/includes/auth_head.php';
?>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?></div>
<?php endif; ?>

<form action="reset_password.php" method="POST">
  <?php echo csrf_field(); ?>
  <div class="form-group">
    <label for="password" class="form-label"><i class="fa-solid fa-key"></i> New Password</label>
    <input type="password" name="password" id="password" class="form-control" required minlength="8" maxlength="255"
      autocomplete="new-password" autofocus>
  </div>
  <div class="form-group">
    <label for="password_confirm" class="form-label"><i class="fa-solid fa-key"></i> Confirm New Password</label>
    <input type="password" name="password_confirm" id="password_confirm" class="form-control" required minlength="8"
      maxlength="255" autocomplete="new-password">
  </div>
  <p class="form-hint">At least 8 characters, with at least one letter and one number. You will be signed out on every
    other device.</p>
  <button type="submit" class="btn btn-primary btn-block" data-loading-text="Saving...">
    <i class="fa-solid fa-floppy-disk"></i> Save New Password
  </button>
</form>

<p class="auth-switch">This link expires at <?php echo date('H:i', strtotime($reset['expires_at'])); ?>.</p>

<?php require __DIR__ . '/includes/auth_foot.php'; ?>
