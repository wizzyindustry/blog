<?php
/**
 * Cookie consent banner. Included at the end of every public / account page.
 * Shown until the visitor chooses; "Cookie settings" links reopen it.
 *  - Essential only: session + consent cookies (always needed). System font.
 *  - Accept all:     also loads Google Fonts (a third-party request).
 * Works without JavaScript (the buttons are links to consent.php).
 * $banner_prefix: '' for root pages, '../' for pages in /account.
 */
$banner_prefix = $banner_prefix ?? '';
$consent_now = cookie_consent();
$return_to = $_SERVER['REQUEST_URI'] ?? '';
?>
<section class="cookie-banner" id="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="cookie-banner-title"
  aria-describedby="cookie-banner-text" <?php echo $consent_now !== '' ? 'hidden' : ''; ?>
  data-secure="<?php echo is_https() ? '1' : '0'; ?>" data-cookie-name="<?php echo CONSENT_COOKIE; ?>">
  <div class="cookie-banner-inner">
    <div class="cookie-banner-icon" aria-hidden="true"><i class="fa-solid fa-cookie-bite"></i></div>
    <div class="cookie-banner-body">
      <h2 id="cookie-banner-title">We value your privacy</h2>
      <p id="cookie-banner-text">
        We use only <strong>essential cookies</strong> to keep you signed in and protect forms. No advertising or tracking.
        With your permission we also load the <strong>Inter font from Google Fonts</strong>.
        <a href="<?php echo $banner_prefix; ?>cookies.php">Cookie Policy</a>
      </p>
    </div>
    <div class="cookie-banner-actions">
      <a class="btn btn-outline btn-sm" data-consent="essential"
        href="<?php echo $banner_prefix; ?>consent.php?choice=essential&amp;return=<?php echo rawurlencode($return_to); ?>">Essential only</a>
      <a class="btn btn-accent btn-sm" data-consent="all"
        href="<?php echo $banner_prefix; ?>consent.php?choice=all&amp;return=<?php echo rawurlencode($return_to); ?>">Accept all</a>
    </div>
  </div>
</section>
