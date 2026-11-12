<?php
/**
 * Privacy Policy
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/legal_layout.php';

$page_title = 'Privacy Policy';
$active_nav = 'legal';
$meta_description = 'How UDOM Student Materials collects, uses, stores and protects your personal data, and your rights over it.';
$meta_canonical = 'privacy.php';
require_once __DIR__ . '/includes/header.php';

legal_page_start('Privacy Policy', 'What we collect, why, how long we keep it, and your rights.', 'fa-user-shield', [
    'summary' => 'Summary',
    'collect' => 'What we collect',
    'use' => 'How we use it',
    'public' => 'What other people can see',
    'share' => 'Who we share it with',
    'security' => 'How we protect it',
    'retention' => 'How long we keep it',
    'rights' => 'Your rights',
    'children' => 'Children',
    'changes' => 'Changes to this policy',
    'contact' => 'Contact',
]);
?>

<section id="summary">
  <h2>Summary</h2>
  <ul>
    <li>We collect only what the site needs to work: your account details, the files you upload, your comments and basic security logs.</li>
    <li>We do not sell your data, show advertising or use tracking or analytics cookies.</li>
    <li>Files are <strong>private</strong> unless you choose <strong>Public</strong>. Public files can be seen and downloaded by anyone.</li>
    <li>You can view, change, download or delete your data at any time from your account.</li>
  </ul>
</section>

<section id="collect">
  <h2>What we collect</h2>
  <h3>When you create an account</h3>
  <p>Your full name, username, email address and password. The password is stored only as a one-way hash, so nobody (including the administrator) can read it. We also record when you accepted these terms and which version.</p>
  <h3>When you use your account</h3>
  <ul>
    <li><strong>Profile:</strong> an optional bio and profile photo.</li>
    <li><strong>Uploads:</strong> the files you upload, their names, titles, descriptions, categories, size, type and whether they are public or private.</li>
    <li><strong>Comments:</strong> the name and text of comments and replies you post, and when you posted or edited them.</li>
    <li><strong>Sign-in history:</strong> the date, IP address and browser of each sign-in, shown to you under <em>Password &amp; Security</em> so you can spot unknown access.</li>
    <li><strong>Activity:</strong> when you were last active, so the administrator can see how many people use the site.</li>
  </ul>
  <h3>When you visit without an account</h3>
  <p>If you comment as a guest, we store the name and comment you enter. Like every website, the server briefly records your IP address to block abuse (for example repeated failed logins or spam). See the <a href="cookies.php">Cookie Policy</a> for the two cookies we use.</p>
  <h3>Images you upload</h3>
  <p>To save space, images are resized and re-compressed in your browser before upload. This also removes hidden photo data (EXIF), such as the GPS location where a photo was taken.</p>
</section>

<section id="use">
  <h2>How we use it</h2>
  <ul>
    <li>To run your account and show you your files, uploads and comments.</li>
    <li>To publish the files and comments you choose to make public.</li>
    <li>To keep the site secure: stopping password guessing, spam and abuse, and investigating problems.</li>
    <li>To produce anonymous totals for the administrator (for example the number of active users or uploads per day).</li>
  </ul>
  <p>We use your data because you asked us to provide the service (your account), because it is needed to keep the site safe, and, for optional third-party fonts, because you consented.</p>
</section>

<section id="public">
  <h2>What other people can see</h2>
  <ul>
    <li><strong>Public files:</strong> the file, its title, description, category, upload date and your full name and photo are shown on the Materials and Shared Files pages, and anyone can download them.</li>
    <li><strong>Comments:</strong> your name and comment text are public. Comments posted while signed in show your account name.</li>
    <li><strong>Private files, your email address, sign-in history and IP addresses</strong> are never shown to other users.</li>
  </ul>
  <p>The site administrator can see account details and all files (public and private) in order to run and moderate the site.</p>
</section>

<section id="share">
  <h2>Who we share it with</h2>
  <p>We do not sell or rent personal data and do not share it for advertising. The only third parties involved are:</p>
  <ul>
    <li><strong>Cloudflare (cdnjs):</strong> delivers the icon font and chart library, so it receives your IP address when pages load.</li>
    <li><strong>Google Fonts:</strong> only if you choose <em>Accept all</em> in the cookie banner. Otherwise your device's own font is used.</li>
    <li><strong>Authorities:</strong> only when the law requires it.</li>
  </ul>
</section>

<section id="security">
  <h2>How we protect it</h2>
  <p>Passwords are hashed. Private files are stored under random names in folders that cannot be opened from the web, and are only served to their owner. Sign-in is rate-limited against guessing, sessions expire after inactivity, and every form is protected against cross-site request forgery. No system is perfectly secure, so please use a strong password that you do not use anywhere else.</p>
</section>

<section id="retention">
  <h2>How long we keep it</h2>
  <ul>
    <li><strong>Account, files and sign-in history:</strong> until you delete your account. Deleting it permanently removes your files, profile and sign-in history.</li>
    <li><strong>Comments:</strong> until you delete them. If you delete your account first, comments you posted stay visible under the name you used.</li>
    <li><strong>Failed logins and abuse checks</strong> (IP address and time): about 24 hours.</li>
    <li><strong>Error logs:</strong> kept only as long as needed to fix problems.</li>
  </ul>
</section>

<section id="rights">
  <h2>Your rights</h2>
  <p>Under applicable data protection law, including Tanzania's Personal Data Protection Act, 2022, you can:</p>
  <ul>
    <li><strong>Access and correct</strong> your data: see and edit it under <a href="account/profile.php">My Profile</a>.</li>
    <li><strong>Download</strong> your files at any time from <a href="account/files.php">My Files</a>.</li>
    <li><strong>Delete</strong> files, comments or your whole account (<a href="account/security.php">Password &amp; Security</a> &rarr; Delete Account).</li>
    <li><strong>Withdraw consent</strong> for third-party fonts with the <em>Cookie settings</em> link at the bottom of every page.</li>
    <li><strong>Object or complain</strong> by contacting us. You may also complain to the national data protection authority.</li>
  </ul>
</section>

<section id="children">
  <h2>Children</h2>
  <p>This site is intended for university students. If you are under 18, please use it with the permission of a parent or guardian.</p>
</section>

<section id="changes">
  <h2>Changes to this policy</h2>
  <p>If we change this policy in a way that affects you, we will update the date at the top of this page and, for important changes, ask you to accept the new version when you sign in.</p>
</section>

<section id="contact">
  <h2>Contact</h2>
  <p>For privacy questions or requests, contact <?php echo site_contact_html(); ?>.</p>
</section>

<?php
legal_page_end();
require_once __DIR__ . '/includes/footer.php';
