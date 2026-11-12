      <footer class="app-footer">
        <span>&copy; <?php echo date('Y'); ?> UDOM Student Materials</span>
        <span><i class="fa-solid fa-lock"></i> Private files are visible only to you</span>
        <nav class="footer-legal" aria-label="Legal">
          <a href="../privacy.php">Privacy</a>
          <a href="../terms.php">Terms</a>
          <a href="../cookies.php">Cookies</a>
          <a href="../faq.php">FAQ</a>
          <button type="button" class="footer-link-btn" data-cookie-settings>Cookie settings</button>
        </nav>
      </footer>
      <?php $banner_prefix = '../'; require __DIR__ . '/../../includes/cookie_banner.php'; ?>
    </div><!-- /.app-main -->
  </div><!-- /.app-shell -->

  <script src="<?php echo asset_url('assets/js/main.js', '../'); ?>"></script>
  <?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>

</html>
