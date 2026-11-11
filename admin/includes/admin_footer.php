<?php
/**
 * Admin Footer Template (closes the layout opened in admin_header.php)
 */
?>
      <footer class="app-footer">
        <span>&copy; <?php echo date('Y'); ?> UDOM Student Materials &bull; Administrative Control Center</span>
        <span><i class="fa-solid fa-shield-halved"></i> Authenticated Admin Session</span>
      </footer>
    </div><!-- /.app-main -->
  </div><!-- /.app-shell -->

<!-- Main JavaScript -->
<script src="<?php echo asset_url('assets/js/main.js', '../'); ?>"></script>
<?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>

</html>
