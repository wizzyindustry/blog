<?php
/**
 * Cookie Policy
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/legal_layout.php';

$page_title = 'Cookie Policy';
$active_nav = 'legal';
$meta_description = 'The cookies UDOM Student Materials uses, why, and how to change your choice. No advertising or tracking cookies.';
$meta_canonical = 'cookies.php';
$session_cookie = is_https() ? '__Host-udom_sid' : 'udom_sid';
require_once __DIR__ . '/includes/header.php';

legal_page_start('Cookie Policy', 'We use as few cookies as possible, and none for advertising or tracking.', 'fa-cookie-bite', [
    'what' => 'What cookies are',
    'list' => 'Cookies we use',
    'third' => 'Third-party content',
    'choice' => 'Your choice',
    'contact' => 'Contact',
]);
?>

<section id="what">
  <h2>What cookies are</h2>
  <p>Cookies are small text files a website stores in your browser. They let the site remember things between pages, such as that you are signed in.</p>
</section>

<section id="list">
  <h2>Cookies we use</h2>
  <div class="table-responsive">
    <table class="data-table legal-table">
      <thead><tr><th>Name</th><th>Purpose</th><th>Type</th><th>Expires</th></tr></thead>
      <tbody>
        <tr>
          <td><code><?php echo $session_cookie; ?></code></td>
          <td>Keeps you signed in, remembers forms between pages and protects them against forgery (CSRF). It cannot be read by scripts.</td>
          <td>Strictly necessary</td>
          <td>When you close the browser, or after inactivity</td>
        </tr>
        <tr>
          <td><code><?php echo CONSENT_COOKIE; ?></code></td>
          <td>Remembers your answer to the cookie banner, so we do not ask again.</td>
          <td>Strictly necessary</td>
          <td>1 year</td>
        </tr>
      </tbody>
    </table>
  </div>
  <p>Strictly necessary cookies cannot be switched off, because the site cannot work without them. We do <strong>not</strong> use analytics, advertising, social media or tracking cookies.</p>
</section>

<section id="third">
  <h2>Third-party content</h2>
  <p>These services do not set cookies through our site, but your browser contacts them, so they receive your IP address:</p>
  <ul>
    <li><strong>Cloudflare cdnjs:</strong> icons and the admin charts. Always on, because pages would not display properly without them.</li>
    <li><strong>Google Fonts:</strong> the Inter typeface. <strong>Optional:</strong> loaded only if you choose <em>Accept all</em>. With <em>Essential only</em> your device's own font is used instead.</li>
  </ul>
</section>

<section id="choice">
  <h2>Your choice</h2>
  <p>You can change your choice at any time:</p>
  <p><button type="button" class="btn btn-primary" data-cookie-settings><i class="fa-solid fa-sliders"></i> Open cookie settings</button></p>
  <p>You can also delete cookies in your browser settings. If you delete the session cookie you will be signed out.</p>
</section>

<section id="contact">
  <h2>Contact</h2>
  <p>Questions about cookies? Contact <?php echo site_contact_html(); ?>. See also our <a href="privacy.php">Privacy Policy</a>.</p>
</section>

<?php
legal_page_end();
require_once __DIR__ . '/includes/footer.php';
