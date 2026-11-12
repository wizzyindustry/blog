<?php
/**
 * User Area Layout (top): sidebar navigation + top bar with profile dropdown.
 * Included after account/auth_check.php, which sets $user.
 * Set $page_title before including.
 */
$account_title = isset($page_title) ? $page_title : 'My Account';
$current_page = basename($_SERVER['PHP_SELF']);

$account_nav = [
    'Main' => [
        'dashboard.php' => ['fa-gauge-high', 'Dashboard'],
        'upload.php' => ['fa-cloud-arrow-up', 'Upload Files'],
        'files.php' => ['fa-folder-open', 'My Files'],
        'gallery.php' => ['fa-images', 'My Images'],
    ],
    'Account' => [
        'profile.php' => ['fa-user-pen', 'My Profile'],
        'security.php' => ['fa-shield-halved', 'Password & Security'],
    ],
];
$storage_pct = min(100, round(((int) $user['storage_used'] / USER_STORAGE_QUOTA) * 100, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo sanitize($account_title); ?> - UDOM Student Materials</title>
  <?php echo render_head_meta(['description' => 'Your UDOM Student Materials account.', 'noindex' => true], '../'); ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css', '../'); ?>">
</head>

<body class="app-body">
  <?php require __DIR__ . '/../../includes/page_loader.php'; ?>

  <div class="app-shell">
    <!-- Sidebar navigation -->
    <aside class="app-sidebar" id="app-sidebar" aria-label="Account navigation">
      <div class="sidebar-brand">
        <a href="dashboard.php" class="sidebar-brand-link">
          <span class="brand-icon brand-icon-sm"><i class="fa-solid fa-graduation-cap"></i></span>
          <span class="sidebar-brand-text">UDOM Materials</span>
        </a>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <?php foreach ($account_nav as $section => $links): ?>
          <div class="sidebar-section"><?php echo sanitize($section); ?></div>
          <?php foreach ($links as $href => [$icon, $label]): ?>
            <a href="<?php echo $href; ?>" class="sidebar-link<?php echo $current_page === $href ? ' active' : ''; ?>"
              <?php echo $current_page === $href ? 'aria-current="page"' : ''; ?>>
              <i class="fa-solid <?php echo $icon; ?>"></i><span><?php echo $label; ?></span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="sidebar-section">Explore</div>
        <a href="../index.php" class="sidebar-link"><i class="fa-solid fa-book-open"></i><span>Study Materials</span></a>
        <a href="../shared.php" class="sidebar-link"><i class="fa-solid fa-share-nodes"></i><span>Shared by Students</span></a>
        <a href="../landing.php" class="sidebar-link"><i class="fa-solid fa-house"></i><span>Website Home</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-storage">
          <div class="sidebar-storage-label">
            <span><i class="fa-solid fa-hard-drive"></i> Storage</span>
            <span><?php echo $storage_pct; ?>%</span>
          </div>
          <div class="progress" role="progressbar" aria-valuenow="<?php echo $storage_pct; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Storage used">
            <div class="progress-bar<?php echo $storage_pct >= 90 ? ' is-danger' : ''; ?>" style="width: <?php echo $storage_pct; ?>%;"></div>
          </div>
          <small><?php echo format_bytes((int) $user['storage_used']); ?> of <?php echo format_bytes(USER_STORAGE_QUOTA); ?></small>
        </div>
      </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="app-main">
      <!-- Top bar -->
      <header class="app-topbar">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title"><?php echo sanitize($account_title); ?></div>

        <div class="topbar-actions">
          <a href="upload.php" class="btn btn-accent btn-sm topbar-upload">
            <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload</span>
          </a>

          <!-- Profile dropdown (top-right) -->
          <div class="profile-menu" data-dropdown>
            <button type="button" class="profile-toggle" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
              <?php echo user_avatar_html($user, '../', 'avatar avatar-sm'); ?>
              <span class="profile-name"><?php echo sanitize($user['full_name']); ?></span>
              <i class="fa-solid fa-chevron-down profile-caret"></i>
            </button>
            <div class="profile-dropdown" role="menu">
              <div class="profile-dropdown-head">
                <?php echo user_avatar_html($user, '../', 'avatar avatar-md'); ?>
                <div>
                  <strong><?php echo sanitize($user['full_name']); ?></strong>
                  <span>@<?php echo sanitize($user['username']); ?></span>
                </div>
              </div>
              <a href="profile.php" role="menuitem"><i class="fa-solid fa-user-pen"></i> My Profile</a>
              <a href="files.php" role="menuitem"><i class="fa-solid fa-folder-open"></i> My Files</a>
              <a href="upload.php" role="menuitem"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</a>
              <a href="security.php" role="menuitem"><i class="fa-solid fa-shield-halved"></i> Password &amp; Security</a>
              <a href="../index.php" role="menuitem"><i class="fa-solid fa-book-open"></i> Study Materials</a>
              <form action="logout.php" method="POST" class="profile-dropdown-logout">
                <?php echo csrf_field(); ?>
                <button type="submit" role="menuitem" data-loading-text="Signing out...">
                  <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                </button>
              </form>
            </div>
          </div>
        </div>
      </header>
