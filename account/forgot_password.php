<?php
/**
 * Forgot password: emails a one-time reset link.
 * Always shows the same message, so nobody can use this page to find out which emails have accounts.
 */
require_once __DIR__ . '/../config/db.php';

start_deferred_output(); // The email is sent after the page is delivered (see below)
start_session();
if (current_user()) {
    header('Location: security.php');
    exit;
}
header('Referrer-Policy: no-referrer');

$sent = false;
$error = get_flash('error'); // e.g. "This reset link ... has expired" from reset_password.php
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $started = microtime(true);
    $email = mb_strtolower(trim(input_text($_POST, 'email')));
    $ip = client_ip();

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (rate_limited('reset', $ip, RESET_REQUESTS_PER_IP, 15)) {
        $error = 'Too many reset requests from your network. Please wait 15 minutes and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $error = 'Please enter the email address of your account.';
    } else {
        rate_hit('reset', $ip);
        $base = reset_link_base();
        if ($base === null) {
            error_log('Password reset: APP_URL is not set, so no reset link was emailed (see README)');
        } elseif (!rate_limited('resetacct', account_key($email), RESET_REQUESTS_PER_ACCOUNT, 60)) {
            rate_hit('resetacct', account_key($email));
            $stmt = $pdo->prepare('SELECT id, full_name, username, email, status FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch();
            // Unknown or suspended accounts get no email, but the page says exactly the same thing
            if ($user && $user['status'] === 'active') {
                $token = create_password_reset($pdo, $user);
                if ($token !== null) {
                    // Sent AFTER the page is delivered: SMTP takes seconds, and that delay must not
                    // reveal that this email belongs to an account
                    $link = $base . 'account/reset_password.php?token=' . $token;
                    defer_after_response(function () use ($user, $link) {
                        if (!send_password_reset_email($user, $link)) {
                            error_log('Password reset: the email to user #' . (int) $user['id'] . ' could not be sent');
                        }
                    });
                }
            }
        }
        $sent = true;
        // Same response time whether or not an email was sent (no account discovery by timing)
        $wait = RESET_MIN_RESPONSE_SECONDS - (microtime(true) - $started);
        if ($wait > 0) {
            usleep((int) ($wait * 1000000));
        }
    }
}

$auth_title = 'Forgot Password';
$auth_subtitle = 'We will email you a link to choose a new password';
require __DIR__ . '/includes/auth_head.php';
?>

<?php if ($sent): ?>
  <div class="alert alert-success" role="status">
    <i class="fa-solid fa-envelope-circle-check"></i>
    <span class="alert-text">If an account uses <strong><?php echo sanitize($email); ?></strong>, a reset link is on its way.
      It works once and expires in <?php echo RESET_TOKEN_TTL_MINUTES; ?> minutes.</span>
  </div>
  <p class="text-muted">Nothing arrived after a few minutes? Check your spam folder, make sure you used the email
    address of your account, or <a href="forgot_password.php">try again</a>.</p>
<?php else: ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?></div>
  <?php endif; ?>

  <form action="forgot_password.php" method="POST">
    <?php echo csrf_field(); ?>
    <div class="form-group">
      <label for="email" class="form-label"><i class="fa-solid fa-envelope"></i> Account email</label>
      <input type="email" name="email" id="email" class="form-control" value="<?php echo sanitize($email); ?>"
        required maxlength="190" autocomplete="email" autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-loading-text="Sending...">
      <i class="fa-solid fa-paper-plane"></i> Send Reset Link
    </button>
  </form>
<?php endif; ?>

<p class="auth-switch">Remembered it? <a href="login.php">Sign in</a></p>

<?php require __DIR__ . '/includes/auth_foot.php'; ?>
