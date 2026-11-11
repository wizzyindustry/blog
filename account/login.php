<?php
/**
 * User Login (username or email + password)
 * Same protections as the admin login: prepared statements, CSRF token,
 * timing-safe checks, 5 failures per IP per 15 minutes, session regeneration.
 */
require_once __DIR__ . '/../config/db.php';

start_session();

// Only same-folder page names are accepted as a return address (no open redirects)
$next = basename(input_text($_GET, 'next', input_text($_POST, 'next', 'dashboard.php')));
if (!preg_match('/^[a-z_]+\.php$/', $next) || !is_file(__DIR__ . '/' . $next)
    || in_array($next, ['login.php', 'logout.php', 'register.php', 'auth_check.php'], true)) {
    $next = 'dashboard.php';
}

if (current_user()) {
    header('Location: ' . $next);
    exit;
}

// Valid bcrypt hash of a random value: keeps response time the same for unknown accounts
const USER_DUMMY_HASH = '$2y$10$vEGjxeVLC.Mcw6lFwgSv0eTmngPJWNsORMmSM6TCMURmLwckJu5mu';

$ip = client_ip();
$error_msg = '';
$login = '';
$locked = recent_failed_logins($pdo, 'user', $ip) >= LOGIN_MAX_ATTEMPTS_PER_IP;
$flash_success = get_flash('success');

if ($locked) {
    $error_msg = 'Too many failed login attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim(input_text($_POST, 'login'));
    $password = input_text($_POST, 'password');

    if (!csrf_valid()) {
        $error_msg = 'Your session expired. Please try again.';
    } elseif ($login === '' || $password === '') {
        $error_msg = 'Please enter your username or email and your password.';
    } elseif (mb_strlen($login) > 190 || strlen($password) > 255) {
        password_verify($password, USER_DUMMY_HASH);
        record_failed_login($pdo, 'user', $ip, $login);
        $error_msg = 'Invalid username/email or password.';
    } else {
        try {
            $column = strpos($login, '@') !== false ? 'email' : 'username';
            $stmt = $pdo->prepare('SELECT id, username, password, status FROM users WHERE ' . $column . ' = :login LIMIT 1');
            $stmt->execute([':login' => $column === 'email' ? mb_strtolower($login) : $login]);
            $found = $stmt->fetch();

            // Always verify a hash, even for unknown users (timing-safe)
            $ok = password_verify($password, $found ? $found['password'] : USER_DUMMY_HASH);

            if ($found && $ok && $found['status'] !== 'active') {
                $error_msg = 'This account has been suspended. Please contact the administrator.';
            } elseif ($found && $ok) {
                if (password_needs_rehash($found['password'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                        ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $found['id']]);
                }
                clear_failed_logins($pdo, 'user', $ip);
                log_in_user($pdo, $found);
                header('Location: ' . $next);
                exit;
            } else {
                record_failed_login($pdo, 'user', $ip, $login);
                $error_msg = 'Invalid username/email or password.';
                $remaining = LOGIN_MAX_ATTEMPTS_PER_IP - recent_failed_logins($pdo, 'user', $ip);
                if ($remaining <= 0) {
                    $locked = true;
                    $error_msg = 'Too many failed login attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes and try again.';
                } elseif ($remaining <= 2) {
                    $error_msg .= ' ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' left before a temporary lock.';
                }
            }
        } catch (PDOException $e) {
            $error_msg = 'Login is temporarily unavailable. Please try again.';
        }
    }
}

$auth_title = 'Sign In';
$auth_subtitle = 'Access your UDOM Student Materials account';
require __DIR__ . '/includes/auth_head.php';
?>

<?php if ($flash_success !== ''): ?>
  <div class="alert alert-success" role="status"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
<?php endif; ?>
<?php if ($error_msg !== ''): ?>
  <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error_msg); ?></div>
<?php endif; ?>

<form action="login.php" method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="next" value="<?php echo sanitize($next); ?>">

  <div class="form-group">
    <label for="login" class="form-label"><i class="fa-solid fa-user"></i> Username or Email</label>
    <input type="text" name="login" id="login" class="form-control" value="<?php echo sanitize($login); ?>"
      required maxlength="190" autocomplete="username" <?php echo $locked ? 'disabled' : 'autofocus'; ?>>
  </div>

  <div class="form-group">
    <label for="password" class="form-label"><i class="fa-solid fa-key"></i> Password</label>
    <input type="password" name="password" id="password" class="form-control"
      required maxlength="255" autocomplete="current-password" <?php echo $locked ? 'disabled' : ''; ?>>
  </div>

  <button type="submit" class="btn btn-primary btn-block" data-loading-text="Signing in..." <?php echo $locked ? 'disabled' : ''; ?>>
    <i class="fa-solid fa-right-to-bracket"></i> Sign In
  </button>
</form>

<p class="auth-switch">New here? <a href="register.php">Create an account</a></p>

<?php require __DIR__ . '/includes/auth_foot.php'; ?>
