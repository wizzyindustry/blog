<?php
/**
 * Shared layout for the Privacy Policy, Terms & Conditions, Cookie Policy and FAQ pages:
 * page hero, "on this page" table of contents and a readable text column.
 */

/** Contact line for legal pages. Set CONTACT_EMAIL in config/secrets.php to show an email link. */
function site_contact_html(): string
{
    $email = (string) app_config('CONTACT_EMAIL', '');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '<a href="mailto:' . sanitize($email) . '">' . sanitize($email) . '</a>';
    }
    return 'the site administrator';
}

/** Open the page. $toc = [anchor => heading]. */
function legal_page_start(string $title, string $intro, string $icon, array $toc, string $updated = LEGAL_VERSION): void
{
    ?>
    <section class="hero-section hero-compact legal-hero">
      <div class="container">
        <h1 class="hero-title"><i class="fa-solid <?php echo $icon; ?>"></i> <?php echo sanitize($title); ?></h1>
        <p class="hero-subtitle"><?php echo sanitize($intro); ?></p>
        <?php if ($updated !== ''): ?>
          <p class="legal-updated">Last updated: <?php echo date('j F Y', strtotime($updated)); ?></p>
        <?php endif; ?>
      </div>
    </section>
    <main class="main-content">
      <div class="container legal-layout">
        <aside class="legal-toc" aria-label="On this page">
          <p class="legal-toc-title">On this page</p>
          <ol>
            <?php foreach ($toc as $anchor => $heading): ?>
              <li><a href="#<?php echo $anchor; ?>"><?php echo sanitize($heading); ?></a></li>
            <?php endforeach; ?>
          </ol>
          <div class="legal-toc-links">
            <a href="privacy.php">Privacy Policy</a>
            <a href="terms.php">Terms &amp; Conditions</a>
            <a href="cookies.php">Cookie Policy</a>
            <a href="faq.php">FAQ</a>
          </div>
        </aside>
        <article class="legal-content">
    <?php
}

function legal_page_end(): void
{
    ?>
        </article>
      </div>
    </main>
    <?php
}
