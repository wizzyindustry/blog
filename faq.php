<?php
/**
 * Frequently Asked Questions (accordion + FAQPage structured data for search engines)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/legal_layout.php';
require_once __DIR__ . '/includes/faq_data.php';

$faq = faq_groups();

// FAQPage structured data (plain-text answers)
$ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach ($faq as $items) {
    foreach ($items as [$q, $a]) {
        $ld['mainEntity'][] = ['@type' => 'Question', 'name' => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(html_entity_decode(strip_tags($a), ENT_QUOTES, 'UTF-8'))]];
    }
}

$page_title = 'FAQ';
$active_nav = 'faq';
$meta_description = 'Answers to common questions about UDOM Student Materials: accounts, uploading and sharing files, comments, privacy and security.';
$meta_canonical = 'faq.php';
require_once __DIR__ . '/includes/header.php';

$toc = [];
foreach (array_keys($faq) as $group) {
    $toc[slugify($group)] = $group;
}
legal_page_start('Frequently Asked Questions', 'Quick answers about accounts, files, comments and privacy.', 'fa-circle-question', $toc, '');
?>

<div class="faq-search">
  <label for="faq-filter" class="visually-hidden">Search the FAQ</label>
  <i class="fa-solid fa-magnifying-glass"></i>
  <input type="search" id="faq-filter" class="form-control" placeholder="Search questions..." autocomplete="off">
  <p class="text-muted faq-empty" id="faq-empty" hidden>No questions match. Try another word, or contact <?php echo site_contact_html(); ?>.</p>
</div>

<?php foreach ($faq as $group => $items): ?>
  <section id="<?php echo slugify($group); ?>" class="faq-group">
    <h2><?php echo sanitize($group); ?></h2>
    <?php foreach ($items as $i => [$q, $a]): ?>
      <details class="faq-item">
        <summary><span><?php echo sanitize($q); ?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
        <div class="faq-answer"><p><?php echo $a; // Trusted, written in this file ?></p></div>
      </details>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<section class="faq-cta">
  <h2>Still have a question?</h2>
  <p>Contact <?php echo site_contact_html(); ?>, or ask in the comments under any study material.</p>
</section>

<script type="application/ld+json"><?php echo json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

<?php
legal_page_end();
require_once __DIR__ . '/includes/footer.php';
