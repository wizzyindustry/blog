<?php
/**
 * Admin Login Page
 * UDOM Student Materials Blog
 *
 * Security layers:
 *  1. SQL injection: the query is a PDO prepared statement with the username
 *     bound as a typed string parameter (PDO::PARAM_STR) and real (non-emulated)
 *     prepares, so input is never mixed into the SQL text. On top of that,
 *     the username is checked against a strict whitelist (letters, digits, . _ -)
 *     before it ever reaches the database.
 *  2. Brute force: after 5 failed attempts from one IP address in 15 minutes,
 *     logins from that IP are blocked until the window passes.
 *  3. CSRF: the form carries a per-session token.
 *  4. User enumeration: the same error message and similar response time
 *     whether the username exists or not.
 *  5. Session fixation: the session ID is regenerated after a successful login.
 */
require_once __DIR__ . '/../config/db.php';

start_session();

// Already logged in: go straight to the dashboard
if (!empty($_SESSION['admin_logged_in'])) {
  header('Location: dashboard.php');
  exit;
}

// Brute-force limits and helpers are shared with the user login (includes/users.php)
const LOGIN_MAX_ATTEMPTS = LOGIN_MAX_ATTEMPTS_PER_IP;
const LOGIN_WINDOW_MINUTES = LOGIN_LOCK_MINUTES;

// Valid bcrypt hash of a random value. Used when the username does not exist so
// password_verify() still runs and response time does not reveal valid usernames.
const DUMMY_PASSWORD_HASH = '$2y$10$vEGjxeVLC.Mcw6lFwgSv0eTmngPJWNsORMmSM6TCMURmLwckJu5mu';

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$error_msg = '';
$username = '';
$locked = recent_failed_logins($pdo, 'admin', $client_ip) >= LOGIN_MAX_ATTEMPTS;

if ($locked) {
  $error_msg = 'Too many failed login attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim(input_text($_POST, 'username'));
  // Password is not trimmed: spaces may be part of it
  $password = input_text($_POST, 'password');

  if (!csrf_valid()) {
    $error_msg = 'Your session expired. Please try again.';
  } elseif ($username === '' || $password === '') {
    $error_msg = 'Please enter both username and password.';
  } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen($password) > 255) {
    // Input that can never be a valid account (e.g. contains quotes, spaces,
    // SQL keywords with symbols) is rejected before touching the database.
    password_verify($password, DUMMY_PASSWORD_HASH); // Keep timing consistent
    record_failed_login($pdo, 'admin', $client_ip, $username);
    $error_msg = 'Invalid username or password.';
  } else {
    try {
      // Prepared statement: the username is sent separately from the SQL text,
      // so it can never change the query (SQL injection is not possible).
      $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = :username LIMIT 1');
      $stmt->bindValue(':username', $username, PDO::PARAM_STR);
      $stmt->execute();
      $admin = $stmt->fetch();

      // Always run password_verify, even for unknown users (timing-safe)
      $hash = $admin ? $admin['password'] : DUMMY_PASSWORD_HASH;
      $password_ok = password_verify($password, $hash);

      if ($admin && $password_ok) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        unset($_SESSION['csrf_token']);
        csrf_token(); // Fresh CSRF token for the admin session

        // Upgrade the stored hash if PHP's default algorithm/cost has changed
        if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
          $rehash = $pdo->prepare('UPDATE admins SET password = :password WHERE id = :id');
          $rehash->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
          $rehash->bindValue(':id', (int) $admin['id'], PDO::PARAM_INT);
          $rehash->execute();
        }

        clear_failed_logins($pdo, 'admin', $client_ip);
        header('Location: dashboard.php');
        exit;
      }

      record_failed_login($pdo, 'admin', $client_ip, $username);
      $error_msg = 'Invalid username or password.';
    } catch (PDOException $e) {
      // Never show SQL details to the visitor
      $error_msg = 'Login is temporarily unavailable. Please try again.';
    }
  }

  // Show remaining attempts / lock immediately after the 5th failure
  if ($error_msg === 'Invalid username or password.') {
    $remaining = LOGIN_MAX_ATTEMPTS - recent_failed_logins($pdo, 'admin', $client_ip);
    if ($remaining <= 0) {
      $locked = true;
      $error_msg = 'Too many failed login attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } elseif ($remaining <= 2) {
      $error_msg .= ' ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' left before a temporary lock.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Login - UDOM Student Materials</title>

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 (icons only, no emojis) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css', '../'); ?>">
</head>

<body class="login-body">
  <?php require __DIR__ . '/../includes/page_loader.php'; // Loading screen ?>

  <div class="login-card">
    <div class="login-header">
      <div class="brand-icon" style="margin: 0 auto 12px auto;">
        <i class="fa-solid fa-graduation-cap"></i>
      </div>
      <h1>UDOM Student Materials</h1>
      <p>Administrator Sign In</p>
    </div>

    <div class="login-body-content">
      <?php if ($error_msg !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error_msg); ?>
        </div>
      <?php endif; ?>

      <form action="login.php" method="POST" autocomplete="off">
        <?php echo csrf_field(); ?>

        <div class="form-group">
          <label for="username" class="form-label">
            <i class="fa-solid fa-user"></i> Username
          </label>
          <input type="text" name="username" id="username" class="form-control" placeholder="Enter admin username"
            value="<?php echo sanitize($username); ?>" required minlength="3" maxlength="50"
            pattern="[A-Za-z0-9_.\-]{3,50}" title="Letters, numbers, dot, underscore or hyphen (3-50 characters)"
            autocomplete="username" <?php echo $locked ? 'disabled' : 'autofocus'; ?>>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">
            <i class="fa-solid fa-key"></i> Password
          </label>
          <input type="password" name="password" id="password" class="form-control" placeholder="Enter password"
            required maxlength="255" autocomplete="current-password" <?php echo $locked ? 'disabled' : ''; ?>>
        </div>

        <div style="margin-bottom: 20px;">
          <button type="submit" class="btn btn-primary" style="width: 100%;" data-loading-text="Signing in..." <?php echo $locked ? 'disabled' : ''; ?>>
            <i class="fa-solid fa-right-to-bracket"></i> Sign In
          </button>
        </div>
      </form>

      <div style="text-align: center;">
        <a href="../landing.php" style="font-size: 14px; font-weight: 500;">
          <i class="fa-solid fa-arrow-left"></i> Return to Website
        </a>
      </div>
    </div>
  </div>

  <!-- Loading screen + button spinners -->
  <script src="<?php echo asset_url('assets/js/main.js', '../'); ?>"></script>
</body>

</html>
