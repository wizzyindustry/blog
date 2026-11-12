<?php
/**
 * Terms & Conditions
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/legal_layout.php';

$page_title = 'Terms & Conditions';
$active_nav = 'legal';
$meta_description = 'The rules for using UDOM Student Materials: accounts, uploading and sharing files, comments, and our responsibilities.';
$meta_canonical = 'terms.php';
require_once __DIR__ . '/includes/header.php';

legal_page_start('Terms & Conditions', 'The rules for using the site. By creating an account or posting content you agree to them.', 'fa-file-contract', [
    'accept' => 'Accepting these terms',
    'accounts' => 'Your account',
    'content' => 'Your files and comments',
    'rules' => 'What is not allowed',
    'copyright' => 'Copyright',
    'moderation' => 'Moderation',
    'availability' => 'Availability and liability',
    'ending' => 'Closing an account',
    'changes' => 'Changes',
    'law' => 'Governing law',
    'contact' => 'Contact',
]);
?>

<section id="accept">
  <h2>1. Accepting these terms</h2>
  <p>You can read the study materials without an account. When you create an account, upload a file or post a comment, you agree to these Terms &amp; Conditions and to our <a href="privacy.php">Privacy Policy</a>. If you do not agree, please do not use those features.</p>
</section>

<section id="accounts">
  <h2>2. Your account</h2>
  <ul>
    <li>Give your real name and a working email address, and keep them up to date.</li>
    <li>Keep your password secret. You are responsible for what happens under your account. Tell us if you think someone else has used it.</li>
    <li>One person, one account. Do not create accounts for other people or automated accounts.</li>
  </ul>
</section>

<section id="content">
  <h2>3. Your files and comments</h2>
  <ul>
    <li><strong>You keep ownership</strong> of everything you upload or write.</li>
    <li>Files are <strong>private</strong> by default. When you make a file <strong>public</strong>, you allow the site to store, display and let anyone download it, and to show your name with it. You can make it private or delete it at any time; copies other people already downloaded cannot be recalled.</li>
    <li>Comments are public. Only you can edit or delete your comments. Deleting a comment that has replies leaves a "deleted" placeholder so other people's replies stay.</li>
    <li>Each account has a storage limit and upload limits, and only the listed file types are accepted. Images are compressed automatically.</li>
  </ul>
</section>

<section id="rules">
  <h2>4. What is not allowed</h2>
  <p>Do not upload, share or post anything that:</p>
  <ul>
    <li>you do not have the right to share (see Copyright below), including leaked or unreleased examination papers;</li>
    <li>contains viruses, malware or anything designed to harm devices or the site;</li>
    <li>is illegal, hateful, threatening, harassing, sexually explicit, or reveals someone else's private information;</li>
    <li>is spam, advertising, or misleading (for example pretending to be official university material when it is not);</li>
    <li>breaks university rules on academic integrity, such as sharing work for others to submit as their own.</li>
  </ul>
  <p>Do not try to break into accounts, bypass limits or security, scrape the site in bulk, or disrupt it for others.</p>
</section>

<section id="copyright">
  <h2>5. Copyright</h2>
  <p>Only share your own work, or material you have permission to share (for example notes your lecturer has allowed students to distribute). Textbooks and other copyrighted works may not be uploaded without the owner's permission. If you believe something on the site infringes your rights, contact us with a link to it and we will review it promptly.</p>
</section>

<section id="moderation">
  <h2>6. Moderation</h2>
  <p>The administrator may make files private, delete files or comments, and suspend or delete accounts that break these terms, with or without notice. Repeated or serious breaches lead to a permanent block.</p>
</section>

<section id="availability">
  <h2>7. Availability and liability</h2>
  <p>The site and its materials are provided free of charge, "as is". We work to keep them accurate and available, but we cannot guarantee that every material is correct, complete or up to date, or that the site will never be unavailable. Always check important information (such as exam dates) with official university sources. Keep your own copies of important files. To the extent the law allows, we are not liable for losses caused by using or being unable to use the site.</p>
</section>

<section id="ending">
  <h2>8. Closing an account</h2>
  <p>You can delete your account at any time under <a href="account/security.php">Password &amp; Security</a>. This permanently deletes your files. We may close accounts that break these terms or have been inactive for a long time, after trying to warn you where possible.</p>
</section>

<section id="changes">
  <h2>9. Changes</h2>
  <p>We may update these terms. The date at the top shows the current version. For important changes we will ask you to accept the new version. If you keep using the site after a change, the new terms apply.</p>
</section>

<section id="law">
  <h2>10. Governing law</h2>
  <p>These terms are governed by the laws of the United Republic of Tanzania.</p>
</section>

<section id="contact">
  <h2>11. Contact</h2>
  <p>Questions about these terms? Contact <?php echo site_contact_html(); ?>.</p>
</section>

<?php
legal_page_end();
require_once __DIR__ . '/includes/footer.php';
