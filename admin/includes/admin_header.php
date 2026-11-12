<?php
/**
 * Admin Header Template: sidebar navigation + top bar with profile dropdown.
 * Included by every page inside /admin after auth_check.php.
 */
start_session();
$admin_page_title = isset($page_title) ? $page_title . ' - UDOM Admin' : 'Admin Panel - UDOM Student Materials';
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_username'] ?? 'Admin';

// Users seen in the last few minutes (badge next to "Users")
try {
    $sidebar_online = (int) $pdo->query(
        'SELECT COUNT(*) FROM users WHERE last_seen_at >= (NOW() - INTERVAL ' . USER_ONLINE_MINUTES . ' MINUTE)'
    )->fetchColumn();
} catch (PDOException $e) {
    $sidebar_online = 0; // users table not created yet (run sql/upgrade_v2_users.sql)
}

$admin_nav = [
    'Overview' => [
        'dashboard.php' => ['fa-gauge-high', 'Dashboard', []],
    ],
    'Content' => [
        'posts.php' => ['fa-newspaper', 'Posts', ['edit.php']],
        'create.php' => ['fa-plus', 'New Post', []],
        'categories.php' => ['fa-folder-tree', 'Categories', []],
    ],
    'Community' => [
        'users.php' => ['fa-users', 'Users', ['user.php']],
        'user_files.php' => ['fa-cloud-arrow-up', 'User Uploads', []],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo htmlspecialchars($admin_page_title, ENT_QUOTES, 'UTF-8'); ?></title>

  <?php echo render_head_meta(['description' => 'Administration panel.', 'noindex' => true], '../'); ?>

  <!-- Font Awesome 6 (icons only, no emojis) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css', '../'); ?>">
</head>

<body class="admin-body app-body">
  <?php require __DIR__ . '/../../includes/page_loader.php'; // Loading screen ?>

  <div class="app-shell">
    <!-- Admin sidebar -->
    <aside class="app-sidebar" id="app-sidebar" aria-label="Admin navigation">
      <div class="sidebar-brand">
        <a href="dashboard.php" class="sidebar-brand-link">
          <span class="brand-icon brand-icon-sm"><i class="fa-solid fa-graduation-cap"></i></span>
          <span class="sidebar-brand-text">UDOM Materials</span>
          <span class="admin-badge">Admin</span>
        </a>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <?php foreach ($admin_nav as $section => $links): ?>
          <div class="sidebar-section"><?php echo $section; ?></div>
          <?php foreach ($links as $href => [$icon, $label, $also]): ?>
            <?php $is_active = $current_page === $href || in_array($current_page, $also, true); ?>
            <a href="<?php echo $href; ?>" class="sidebar-link<?php echo $is_active ? ' active' : ''; ?>"
              <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
              <i class="fa-solid <?php echo $icon; ?>"></i><span><?php echo $label; ?></span>
              <?php if ($href === 'users.php' && $sidebar_online > 0): ?>
                <span class="sidebar-badge" title="Online now"><?php echo number_format($sidebar_online); ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="sidebar-section">Website</div>
        <a href="../index.php" target="_blank" rel="noopener" class="sidebar-link">
          <i class="fa-solid fa-arrow-up-right-from-square"></i><span>View Site</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <a href="password.php" class="sidebar-link<?php echo $current_page === 'password.php' ? ' active' : ''; ?>">
          <i class="fa-solid fa-key"></i><span>Change Password</span>
        </a>
        <!-- POST + CSRF: another website cannot sign the admin out -->
        <form action="logout.php" method="POST">
          <?php echo csrf_field(); ?>
          <button type="submit" class="sidebar-link sidebar-logout">
            <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
          </button>
        </form>
      </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="app-main">
      <header class="app-topbar">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title"><?php echo sanitize($page_title ?? 'Admin Panel'); ?></div>

        <div class="topbar-actions">
          <a href="create.php" class="btn btn-accent btn-sm topbar-upload"><i class="fa-solid fa-plus"></i> <span>New Post</span></a>

          <div class="profile-menu" data-dropdown>
            <button type="button" class="profile-toggle" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
              <span class="avatar avatar-sm avatar-initials avatar-admin" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
              <span class="profile-name"><?php echo sanitize($admin_name); ?></span>
              <i class="fa-solid fa-chevron-down profile-caret"></i>
            </button>
            <div class="profile-dropdown" role="menu">
              <div class="profile-dropdown-head">
                <span class="avatar avatar-md avatar-initials avatar-admin" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                <div>
                  <strong><?php echo sanitize($admin_name); ?></strong>
                  <span>Administrator</span>
                </div>
              </div>
              <a href="dashboard.php" role="menuitem"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
              <a href="users.php" role="menuitem"><i class="fa-solid fa-users"></i> Manage Users</a>
              <a href="posts.php" role="menuitem"><i class="fa-solid fa-newspaper"></i> Manage Posts</a>
              <a href="../index.php" target="_blank" rel="noopener" role="menuitem"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Site</a>
              <a href="password.php" role="menuitem"><i class="fa-solid fa-key"></i> Change Password</a>
              <form action="logout.php" method="POST" class="profile-dropdown-logout">
                <?php echo csrf_field(); ?>
                <button type="submit" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
              </form>
            </div>
          </div>
        </div>
      </header>
