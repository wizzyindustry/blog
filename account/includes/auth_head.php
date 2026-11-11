<?php
/**
 * Top of the standalone sign-in / register pages (no sidebar).
 * Set $auth_title and $auth_subtitle (and optionally $auth_wide) before including.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo sanitize($auth_title); ?> - UDOM Student Materials</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css', '../'); ?>">
</head>

<body class="login-body">
  <?php require __DIR__ . '/../../includes/page_loader.php'; ?>

  <div class="login-card<?php echo !empty($auth_wide) ? ' login-card-wide' : ''; ?>">
    <div class="login-header">
      <a href="../landing.php" class="brand-icon" style="margin: 0 auto 12px auto;" aria-label="UDOM Student Materials home">
        <i class="fa-solid fa-graduation-cap"></i>
      </a>
      <h1><?php echo sanitize($auth_title); ?></h1>
      <p><?php echo sanitize($auth_subtitle); ?></p>
    </div>

    <div class="login-body-content">
