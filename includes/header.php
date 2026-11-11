<?php
/**
 * Header Include
 * UDOM Student Materials Blog
 */
start_session();

$page_title = isset($page_title) ? $page_title . ' - UDOM Student Materials' : 'UDOM Student Materials - Academic Resources & Knowledge Hub';
$active_nav = isset($active_nav) ? $active_nav : 'home';
$active_category = isset($active_category) ? $active_category : '';
$nav_categories = get_categories(); // Live list from the categories table

// Logged-in student (for the profile menu); browsing the site counts as activity
try {
  $site_user = current_user();
  if ($site_user) {
    touch_user_activity();
  }
} catch (PDOException $e) {
  $site_user = null; // users table not created yet
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 Free CDN ONLY (No Emojis) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Main Stylesheet (Strict Solid Colors, No Gradients) -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css'); ?>">
</head>

<body>
  <?php require __DIR__ . '/page_loader.php'; // Loading screen ?>

  <!-- Site Navigation Header -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="landing.php" class="brand-logo">
        <span class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></span>
        <span>UDOM Student Materials</span>
      </a>

      <button type="button" class="mobile-menu-toggle" id="mobile-toggle" aria-label="Toggle navigation menu"
        aria-expanded="false" aria-controls="nav-menu">
        <i class="fa-solid fa-bars"></i>
      </button>

      <?php /* Public navigation only. The admin panel is intentionally not linked
               anywhere on the public site (PHP comment, so it never reaches the browser). */ ?>
      <nav aria-label="Main navigation">
        <ul class="nav-menu" id="nav-menu">
          <li>
            <a href="landing.php" class="nav-link <?php echo $active_nav === 'landing' ? 'active' : ''; ?>">
              <i class="fa-solid fa-house"></i> Home
            </a>
          </li>
          <li>
            <a href="index.php" class="nav-link <?php echo $active_nav === 'home' ? 'active' : ''; ?>">
              <i class="fa-solid fa-book-open"></i> Materials
            </a>
          </li>
          <li>
            <a href="shared.php" class="nav-link <?php echo $active_nav === 'shared' ? 'active' : ''; ?>">
              <i class="fa-solid fa-share-nodes"></i> Shared Files
            </a>
          </li>
          <?php if ($nav_categories): ?>
            <!-- Categories dropdown: built from the categories table, so new
                 categories added by the admin appear here automatically -->
            <li class="nav-dropdown" id="nav-dropdown">
              <button type="button" class="nav-link nav-dropdown-toggle <?php echo $active_nav === 'categories' ? 'active' : ''; ?>"
                aria-expanded="false" aria-controls="nav-dropdown-menu">
                <i class="fa-solid fa-folder-tree"></i> Categories <i class="fa-solid fa-chevron-down nav-caret"></i>
              </button>
              <ul class="nav-dropdown-menu" id="nav-dropdown-menu">
                <?php foreach ($nav_categories as $nav_cat): ?>
                  <li>
                    <a href="<?php echo sanitize(home_url('', $nav_cat['name'])); ?>"
                      class="<?php echo $active_category === $nav_cat['name'] ? 'active' : ''; ?>">
                      <i class="fa-solid <?php echo category_icon($nav_cat['name']); ?>"></i>
                      <span><?php echo sanitize($nav_cat['name']); ?></span>
                      <span class="nav-dropdown-count"><?php echo (int) $nav_cat['post_count']; ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endif; ?>
          <?php if ($site_user): ?>
            <li class="nav-account">
              <!-- Profile dropdown (top-right) -->
              <div class="profile-menu profile-menu-site" data-dropdown>
                <button type="button" class="profile-toggle" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                  <?php echo user_avatar_html($site_user, '', 'avatar avatar-sm'); ?>
                  <span class="profile-name"><?php echo sanitize(explode(' ', $site_user['full_name'])[0]); ?></span>
                  <i class="fa-solid fa-chevron-down profile-caret"></i>
                </button>
                <div class="profile-dropdown" role="menu">
                  <div class="profile-dropdown-head">
                    <?php echo user_avatar_html($site_user, '', 'avatar avatar-md'); ?>
                    <div>
                      <strong><?php echo sanitize($site_user['full_name']); ?></strong>
                      <span>@<?php echo sanitize($site_user['username']); ?></span>
                    </div>
                  </div>
                  <a href="account/dashboard.php" role="menuitem"><i class="fa-solid fa-gauge-high"></i> My Dashboard</a>
                  <a href="account/files.php" role="menuitem"><i class="fa-solid fa-folder-open"></i> My Files</a>
                  <a href="account/upload.php" role="menuitem"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</a>
                  <a href="account/profile.php" role="menuitem"><i class="fa-solid fa-user-pen"></i> My Profile</a>
                  <form action="account/logout.php" method="POST" class="profile-dropdown-logout">
                    <?php echo csrf_field(); ?>
                    <button type="submit" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</button>
                  </form>
                </div>
              </div>
            </li>
          <?php else: ?>
            <li class="nav-account">
              <a href="account/login.php" class="nav-link"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>
            </li>
            <li class="nav-account">
              <a href="account/register.php" class="btn btn-accent btn-sm"><i class="fa-solid fa-user-plus"></i> Create Account</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>