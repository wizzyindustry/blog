      <footer class="app-footer">
        <span>&copy; <?php echo date('Y'); ?> UDOM Student Materials</span>
        <span><i class="fa-solid fa-lock"></i> Private files are visible only to you</span>
      </footer>
    </div><!-- /.app-main -->
  </div><!-- /.app-shell -->

  <script src="<?php echo asset_url('assets/js/main.js', '../'); ?>"></script>
  <?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>

</html>
