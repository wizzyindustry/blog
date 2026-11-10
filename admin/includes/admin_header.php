<?php
/**
 * Admin Header Template
 * Included by every page inside /admin after auth_check.php.
 */
start_session();
$admin_page_title = isset($page_title) ? $page_title . ' - UDOM Admin' : 'Admin Panel - UDOM Student Materials';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo htmlspecialchars($admin_page_title, ENT_QUOTES, 'UTF-8'); ?></title>

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 (icons only, no emojis) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">

  <!-- Admin Navigation Bar -->
  <header class="admin-navbar">
    <div class="container admin-header-inner">
      <a href="dashboard.php" class="admin-brand">
        <span class="brand-icon brand-icon-sm"><i class="fa-solid fa-graduation-cap"></i></span>
        <span class="admin-brand-text">UDOM Student Materials</span>
        <span class="admin-badge">Admin</span>
      </a>

      <nav aria-label="Admin navigation">
        <ul class="admin-nav">
          <li>
            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
              <i class="fa-solid fa-table-columns"></i> <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="create.php" class="nav-link <?php echo $current_page === 'create.php' ? 'active' : ''; ?>">
              <i class="fa-solid fa-plus"></i> <span>New Post</span>
            </a>
          </li>
          <li>
            <a href="categories.php" class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
              <i class="fa-solid fa-folder-tree"></i> <span>Categories</span>
            </a>
          </li>
          <li>
            <a href="../index.php" target="_blank" class="nav-link">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> <span>View Site</span>
            </a>
          </li>
          <li>
            <a href="logout.php" class="btn btn-danger btn-sm" title="Log out">
              <i class="fa-solid fa-right-from-bracket"></i>
              <span>Logout (<?php echo sanitize($_SESSION['admin_username'] ?? 'Admin'); ?>)</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </header>
