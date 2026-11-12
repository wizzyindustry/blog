<?php
/**
 * Footer Include
 * UDOM Student Materials Blog
 */
?>
<!-- Site Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <h3>UDOM Student Materials</h3>
        <p class="footer-desc">
          An open educational knowledge and resource platform dedicated to empowering students at the University of
          Dodoma with academic materials, system design resources, historical research, and verified campus
          announcements.
        </p>
        <p class="footer-desc">
          <i class="fa-solid fa-location-dot"></i> University of Dodoma (UDOM), Dodoma, Tanzania
        </p>
      </div>

      <div class="footer-col">
        <h3>Quick Navigation</h3>
        <ul class="footer-links">
          <li><a href="landing.php"><i class="fa-solid fa-chevron-right"></i> Home</a></li>
          <li><a href="index.php"><i class="fa-solid fa-chevron-right"></i> All Materials</a></li>
          <li><a href="shared.php"><i class="fa-solid fa-chevron-right"></i> Shared by Students</a></li>
          <li><a href="account/upload.php"><i class="fa-solid fa-chevron-right"></i> Share a File</a></li>
          <li><a href="landing.php#how-it-works"><i class="fa-solid fa-chevron-right"></i> How It Works</a></li>
          <li><a href="faq.php"><i class="fa-solid fa-chevron-right"></i> FAQ</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h3>Categories</h3>
        <ul class="footer-links">
          <?php /* Live list from the categories table */ ?>
          <?php foreach (get_categories() as $footer_cat): ?>
            <li>
              <a href="<?php echo sanitize(home_url('', $footer_cat['name'])); ?>">
                <i class="fa-solid <?php echo category_icon($footer_cat['name']); ?>"></i>
                <?php echo sanitize($footer_cat['name']); ?>
              </a>
            </li>
          <?php endforeach; ?>
          <li><a href="index.php"><i class="fa-solid fa-layer-group"></i> All Materials</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div>
        &copy; <?php echo date('Y'); ?> UDOM Student Materials. All rights reserved.
      </div>
      <nav class="footer-legal" aria-label="Legal">
        <a href="privacy.php">Privacy Policy</a>
        <a href="terms.php">Terms &amp; Conditions</a>
        <a href="cookies.php">Cookie Policy</a>
        <a href="faq.php">FAQ</a>
        <button type="button" class="footer-link-btn" data-cookie-settings>Cookie settings</button>
      </nav>
    </div>
  </div>
</footer>

<?php $banner_prefix = ''; require __DIR__ . '/cookie_banner.php'; ?>

<!-- Main Vanilla JavaScript -->
<script src="<?php echo asset_url('assets/js/main.js'); ?>"></script>
</body>

</html>