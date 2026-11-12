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
  <?php echo render_head_meta(['description' => $auth_subtitle, 'noindex' => true], '../'); ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
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
