<?php
/**
 * Frequently Asked Questions (accordion + FAQPage structured data for search engines)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/legal_layout.php';

$max_mb = format_bytes(user_max_file_bytes());
$quota = format_bytes(USER_STORAGE_QUOTA);

// group => [ [question, answer HTML], ... ]  (answers use only our own trusted text)
$faq = [
    'Getting started' => [
        ['What is UDOM Student Materials?', 'A free place for University of Dodoma students to find study materials, lecture notes, past papers and campus news, and to share their own notes and files with each other.'],
        ['Do I need an account?', 'No. Anyone can read the materials, view public shared files and comment. You need a free account to <strong>upload files</strong>, keep a profile, and edit or delete your comments from any device. <a href="account/register.php">Create an account</a>.'],
        ['I forgot my password. What do I do?', 'Password reset by email is not available yet. Please contact ' . site_contact_html() . ' from the email address on your account and we will help you get back in.'],
        ['How do I change my name, email or photo?', 'Sign in, open the profile menu at the top-right and choose <strong>My Profile</strong>.'],
    ],
    'Uploading and sharing files' => [
        ['What kinds of files can I upload?', 'PDF, Word (DOC, DOCX, ODT, RTF), PowerPoint (PPT, PPTX, ODP, PPSX), Excel (XLS, XLSX, ODS, CSV), images (JPG, PNG, GIF, WEBP, BMP, HEIC, AVIF), videos (MP4, WEBM, MOV, AVI, MKV...), audio (MP3, WAV, OGG, M4A...), ZIP/RAR/7Z archives and plain text notes (TXT, MD). For safety, programs, scripts, web pages and SVG images are not accepted.'],
        ['How big can my files be?', 'Up to <strong>' . $max_mb . '</strong> per file, up to ' . USER_MAX_FILES_PER_UPLOAD . ' files at a time, and <strong>' . $quota . '</strong> of storage per account. Your storage use is shown in the sidebar.'],
        ['Why was my file rejected?', 'The site checks what is <em>inside</em> a file, not just its name. A file is refused if it does not match the type you chose (for example a picture uploaded as "PDF"), if it is damaged, if it is an Office file with macros, or if it contains program code. Choose the correct type on the upload page and try again.'],
        ['Who can see my files?', 'You choose for every file. <strong>Private</strong> files are visible only to you. <strong>Public</strong> files appear on the Materials and Shared Files pages, and anyone can view and download them. You can switch between the two at any time in <strong>My Files</strong>.'],
        ['Why are my images smaller after uploading?', 'Large images are automatically resized and compressed before upload, so they upload faster, use less of your storage and load quickly for others. This also removes hidden location (GPS) data from photos. Text and older Office files are also compressed on the server and restored automatically when downloaded.'],
        ['Can I share textbooks or exam papers?', 'Only share your own work or material you have permission to share. Copyrighted books and leaked or unreleased exam papers are not allowed. See the <a href="terms.php">Terms &amp; Conditions</a>.'],
    ],
    'Comments' => [
        ['Can I reply to someone\'s comment?', 'Yes. Click <strong>Reply</strong> under any comment. Anyone can reply, including to replies.'],
        ['Can I edit or delete my comment?', 'Yes, but only your own. If you were signed in when you commented, you can edit or delete it at any time from any device. Guest comments can only be changed from the same browser session they were posted from.'],
        ['Why does a comment say "This comment was deleted"?', 'When someone deletes a comment that already has replies, the text is removed but the replies stay, so other people\'s answers are not lost.'],
    ],
    'Privacy and security' => [
        ['What data do you keep about me?', 'Only what the site needs: your account details, your files and comments, and basic security logs. We never sell data or show ads. Read the <a href="privacy.php">Privacy Policy</a>.'],
        ['What cookies do you use?', 'Only two necessary cookies: one keeps you signed in, one remembers your cookie choice. Google Fonts loads only if you accept it. See the <a href="cookies.php">Cookie Policy</a>.'],
        ['Why am I locked out after typing the wrong password?', 'To stop people guessing passwords, sign-in pauses for 15 minutes after several failed attempts. Wait and try again with the correct password.'],
        ['How do I delete my account?', 'Sign in and go to <a href="account/security.php">Password &amp; Security</a> &rarr; <strong>Delete Account</strong>. This permanently deletes your files.'],
        ['How do I report a file or comment?', 'Contact ' . site_contact_html() . ' with a link to it. The administrator can remove content that breaks the rules.'],
    ],
];

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
