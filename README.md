# UDOM Student Materials - Blog Website

A dynamic, database-driven blog for **UDOM Student Materials** (University of Dodoma), built with
Vanilla PHP (PDO), MySQL, HTML5, CSS3, Vanilla JavaScript and Font Awesome 6.

All posts, comments and admin accounts are stored in MySQL, so data persists across refreshes,
browser restarts and server restarts.

> **Going live on InfinityFree:** see [DEPLOY_INFINITYFREE.md](DEPLOY_INFINITYFREE.md). Build the
> upload package with `php deploy/build_infinityfree.php --app-url=https://your-site`.

---

## XAMPP Setup (step by step)

1. **Start the servers.** Open the XAMPP Control Panel and click **Start** next to **Apache** and **MySQL**.
2. **Import the database.** Open <http://localhost/phpmyadmin>, click the **Import** tab, choose
   `sql/database.sql`, and click **Import** (or **Go**). This creates the `udom_blog` database, the three
   tables (`categories`, `posts`, `comments`, `admins`, and `login_attempts` for brute-force protection), the admin account and 8 sample posts with comments.
   Re-importing resets the database to the sample data.
3. **Place the project.** Put this project folder at `C:/xampp/htdocs/udom-blog`.
   (Any folder name works, because all links are relative. This copy currently lives at
   `C:/xampp/htdocs/blog`, so it is also reachable at <http://localhost/blog>.)
4. **Open the site.** Go to <http://localhost/udom-blog>.
   This opens the **landing page** (`landing.php`). The materials list is `index.php`.
   Admin panel: <http://localhost/udom-blog/admin/login.php>. This address is deliberately not linked anywhere
   on the public site, so bookmark it.

### Default admin login

| Username | Password   |
|----------|------------|
| `admin`  | `admin123` |

This default only works once: signing in with it opens a **mandatory password change** (at least 12
characters with upper and lower case letters, a number and a symbol) before any other admin page will load.
The admin area is reachable only from the computer running XAMPP (`admin/.htaccess`).

### Database settings and secrets

Credentials are **not** in the code. They live in `config/secrets.php`, which git ignores. On a new
machine:

1. Copy `config/secrets.example.php` to `config/secrets.php`.
2. As MySQL `root`, create the least-privilege account the site uses:
   `CREATE USER 'udom_app'@'localhost' IDENTIFIED BY '<long random password>';`
   `GRANT SELECT, INSERT, UPDATE, DELETE ON udom_blog.* TO 'udom_app'@'localhost';`
3. Put that password in `config/secrets.php`.

Environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `FORCE_HTTPS`) override the file.
Run the `sql/*.sql` schema and upgrade files as `root` in phpMyAdmin: the site's own account cannot
create or alter tables. While the site is not live it runs on **plain HTTP** (`FORCE_HTTP => true`): HTTPS requests are
redirected to <http://localhost/blog>. To go live, get a real certificate, set `FORCE_HTTP => false` and
`FORCE_HTTPS => true` in `config/secrets.php`, and follow the "WHEN THE SITE GOES LIVE" notes in
`.htaccess` (HTTPS redirect, and a one-year HSTS header).

---

## File Structure

```
config/db.php                 PDO connection (loads includes/functions.php)
includes/functions.php        Shared helpers: categories, queries, rendering, uploads, CSRF, validation
includes/header.php           Public site header and navigation
includes/footer.php           Public site footer
landing.php                   Landing page: explains the project, live stats, categories, latest posts
.htaccess                     Opens landing.php first; blocks web access to config/, includes/, sql/
index.php                     Materials: hero search, category filter, post grid, pagination (6 per page)
post.php?id=                  Single post: image, meta, content, share buttons, comments
search-api.php                JSON endpoint used by live search / filter / pagination
view.php?id=                  Document viewer (PDF / Word / PowerPoint preview, then download)
download.php                  Delivers documents and allowed post images (safe headers, download count)
includes/documents.php        Document upload checks, ZIP reader, DOCX/PPTX preview renderers
admin/login.php               Admin login (prepared statement, whitelist, rate limit, CSRF)
admin/logout.php              Ends the admin session
admin/auth_check.php          Guard included by every protected admin page
admin/dashboard.php           Analytics: online / active users, trends and charts (7 / 30 / 90 days)
admin/posts.php               Paginated, searchable table of posts with Edit / Delete
admin/users.php, user.php     Manage user accounts: search, activity filters, block / unblock / delete
admin/user_files.php          Every file uploaded by users (filter, download, delete)
account/register.php, login.php   Student sign-up and sign-in
account/dashboard.php         User dashboard: storage, uploads by type, recent files
account/upload.php            Upload any file type (drag and drop, progress bar)
account/files.php, gallery.php    My Files (search / filter / sort) and My Images grid
account/profile.php, security.php Profile + photo, password change, sign-in history, delete account
account/file.php              Delivers a user's file to its owner or an admin only
includes/users.php            Accounts, uploads, activity tracking, pagination helpers
sql/upgrade_v2_users.sql      Adds the user tables and indexes to an EXISTING database (no data lost)
sql/seed_load_test.php        CLI load test: php sql/seed_load_test.php 10000 (remove with --clean)
admin/create.php              New post form
admin/edit.php?id=            Edit post form
admin/delete.php?id=          Deletes a post (POST + CSRF token only)
admin/categories.php          Add / edit / delete categories (icon, description, move posts on delete)
admin/includes/               Admin header, footer and the shared post form
assets/css/style.css          All styles (flat colors, no gradients, mobile-first)
assets/js/main.js             Live search, filter, pagination, menu, editor toolbar, validation
uploads/                      Post images (.htaccess blocks script execution)
uploads/documents/            Attached documents (direct access blocked; served by download.php)
sql/database.sql              Full schema and sample data for phpMyAdmin
```

---

## User Accounts & Scaling (v2)

- **Upgrading an existing install:** import `sql/upgrade_v2_users.sql` in phpMyAdmin (safe to run twice; it
  only adds tables, columns and indexes). A fresh import of `sql/database.sql` already includes everything.
- **Students** register at `account/register.php`, then get a sidebar dashboard with a profile menu
  (top-right) to upload any file type: up to 10 files at once, 1 GB storage each. The per-file limit is the
  lower of 100 MB and php.ini's `upload_max_filesize` / `post_max_size` (XAMPP default: 40 MB).
- **Uploads are stored safely:** random names with no extension in `uploads/user_files/` (web access
  denied), served only by `account/file.php`. Only images, PDF, audio, video and plain text open in the
  browser; everything else (including HTML, SVG and scripts) is always downloaded.
- **Admin analytics:** online now (seen in the last 5 minutes), active today / week / month,
  registrations, sign-ins and uploads with change vs. the previous period, plus charts (Chart.js).
- **Scale:** every list is paginated and every filter or chart runs on an indexed column. Tested with
  10,000 users, 10,000 files and 30,000 sign-ins: every admin page loaded in under 0.13 s on XAMPP.
  Try it with `C:\xampp\php\php.exe sql\seed_load_test.php 10000` (demo password: `Password123`).

## Sharing Files with Other Students (v3)

- **Upgrading:** import `sql/upgrade_v3_sharing.sql` (after v2). Safe to run twice.
- **On upload, students choose:**
  1. *What* they are uploading: Any file, PDF, Word, PowerPoint, Excel, Images, Videos, Audio,
     ZIP / Archives or Notes / Text. The file picker only offers that type, and the server rejects
     files that don't match.
  2. *Who* can see it: **Private** (only them) or **Public** (shown on the Materials page).
  3. Optional title, category and description.
- Students can change visibility at any time with one click in **My Files**, or edit the details
  with the pencil button.
- **Public files** appear in a "Shared by Students" section on `index.php` (it follows the search
  and category filter), on `shared.php` (filter by type and category), and each has its own page,
  `shared_file.php?id=` (preview for images, PDF, video, audio and text; download for everything).
  Guests can view and download public files; private files stay private.
- **Admins** see Public / Private in *User Uploads*, can filter by it, and can make any public file
  private (removing it from the Materials page) or delete it. Files of blocked users are hidden.

## Pages, SEO, Privacy and UX (v5)

- **Upgrading:** import `sql/upgrade_v5_legal_compression.sql` as `root` (safe to run twice).
- **Error screens:** `error.php` and `includes/error_page.php` give one branded screen for
  400/401/403/404/405/408/413/429/500/502/503/504.
  - Apache uses them through `ErrorDocument` in `.htaccess`. Those lines contain the project folder,
    `/blog`; change it if you move the site.
  - Server errors show a reference code that also appears in `logs/php-errors.log`.
  - An offline notice appears when the connection drops.
- **New pages:** `faq.php`, `privacy.php`, `terms.php` and `cookies.php`. Set `CONTACT_EMAIL` in
  `config/secrets.php` to show your contact address on them.
- **SEO:** every page has a meta description, a canonical URL and Open Graph / Twitter tags.
  - Favicons live in `favicon.ico` and `assets/img/`, plus `site.webmanifest`.
  - `robots.txt`, `sitemap.xml` and `llms.txt` are generated from the database (`robots.php`,
    `sitemap.php`, `llms.php`). Set `APP_URL` once the site has a public address.
- **Sign-up** requires ticking the Terms & Privacy checkbox. This is enforced on the server, and the
  date and version are stored.
- **Cookie banner:** only essential cookies are used. Google Fonts (Inter) loads only after
  *Accept all*; "Cookie settings" in the footer reopens the banner.
- **Compression:**
  - **In the browser:** images are resized and re-encoded before upload (up to 2560px for uploads,
    1920px for post images, 512px for avatars), which also removes GPS/EXIF data.
  - **On the server:** TXT, MD, CSV, RTF, DOC, XLS, PPT, BMP and WAV files are stored
    gzip-compressed and served back unchanged.
  - **Server-side image re-encoding** needs PHP's GD extension. In `C:\xampp\php\php.ini`, change
    `;extension=gd` to `extension=gd` and restart Apache.
- **UX:** an eye button shows or hides any password, and destructive actions open a confirmation
  dialog instead of the browser's `confirm()` box.

## Password reset by email (v7)

**1. Upgrade the database.** Import `sql/upgrade_v7_password_reset.sql` as `root` (safe to run twice).

**2. Set up email** in `config/secrets.php`:

```php
'APP_URL'     => 'http://localhost/blog',   // REQUIRED on a live site: links in emails use it
'SMTP_HOST'   => 'smtp.gmail.com',
'SMTP_PORT'   => 587,
'SMTP_SECURE' => 'tls',                     // tls (587) | ssl (465)
'SMTP_USER'   => 'your.address@gmail.com',
'SMTP_PASS'   => 'xxxx xxxx xxxx xxxx',     // Gmail: an "App password", not your normal password
'MAIL_FROM'   => '',                        // defaults to SMTP_USER
```

**Brevo (recommended; used by this site).** It's free for 300 emails a day and less likely to
land in spam than Gmail.

| Setting | Value |
|---|---|
| `SMTP_HOST` | `smtp-relay.brevo.com` |
| `SMTP_PORT` | `587` |
| `SMTP_SECURE` | `tls` |
| `SMTP_USER` | the "Login" from **SMTP & API → SMTP** (looks like `...@smtp-brevo.com`) |
| `SMTP_PASS` | an SMTP key generated on the same page (`xsmtpsib-...`) |
| `MAIL_FROM` | a sender address confirmed under **Senders** |

**If the log says `525 Unauthorized IP address`**, the settings are fine. Allow the server's IP
address in Brevo: your name → **Security → Authorized IPs**. Home internet addresses can change,
so check this first if emails stop.

Email is sent **after** the page is delivered, so a slow mail server never slows pages down.
"Forgot password" also answers in the same time whether or not an account exists.

For Gmail:
1. Turn on 2-Step Verification for the account.
2. Go to Google Account → Security → **App passwords**.
3. Create one and paste it as `SMTP_PASS`.

**While `SMTP_HOST` is empty (development), no email is sent.** Each message is saved as an `.eml`
file in `logs/mail/`, which can't be opened from the web, so you can copy the link from it.

**How it works**

- **Requesting a link:** *Sign in → Forgot password?* emails a link that works **once** and
  **expires after 30 minutes**. Asking again cancels the older link.
- **Using the link:** `account/reset_password.php` checks the link, then reloads without the token
  in the address bar, so it doesn't stay in browser history.
- **After the reset:**
  - Every other signed-in device is signed out (`users.password_changed_at`).
  - A "your password was changed" email is sent.
  - The account's sign-in lock is cleared.
- **No account discovery:** unknown emails get exactly the same message, after the same ~2-second
  wait.
- **Tokens:** only a SHA-256 hash of each token is stored.
- **Limits:**
  - 5 requests per network per 15 minutes.
  - 3 emails per account per hour.
  - 20 wrong links per 15 minutes.
- **Forged links blocked:** links are built from `APP_URL` (or `localhost`), never from the
  request's `Host` header. Without `APP_URL`, a live site sends **no** reset emails. The reason
  is written to `logs/php-errors.log`.
- **Changing a password while signed in** (Password & Security) also signs out other devices and
  sends the notification email.

## Blue-green deployment (instant rollback)

`deploy/deploy.ps1` keeps **two complete copies** of the site and switches Apache between them:

```
C:\xampp\deploy\blog\
  releases\blue\    releases\green\     <- two versions of the code; /blog serves ONE of them
  shared\uploads  shared\logs  shared\config\secrets.php   <- shared by both, never copied or lost
  backups\db_*.sql                      <- database backup taken before every switch
  apache-blog.conf  state.json  deploy.log
```

**One-time setup** (PowerShell **as Administrator**, in the project folder):

```powershell
powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 install
```

Then restart Apache **once** (XAMPP Control Panel: Stop, Start). Install:
- copies `uploads`, `logs` and `secrets.php` into `shared\`;
- adds one `IncludeOptional` line to `httpd.conf`, after taking a backup of it.

`/blog` keeps working from `htdocs\blog` until the first deploy.

**Every release** (as Administrator):

```powershell
powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 deploy
```

1. Copies the code into the **idle** colour. The live site is not touched.
2. Runs `php -l` on every PHP file.
3. Tests the idle colour through its private preview address (`/blog-blue` or `/blog-green`, this
   computer only). It checks `health.php` and 10 key pages for HTTP 200 with no PHP errors, that a
   missing page gives 404, and that `secrets.php` can't be opened.
4. Backs up the database (keeps the last 10 backups).
5. Points `/blog` at the new colour and restarts Apache gracefully, so requests in progress finish.
6. Watches the live site for 30 seconds. If it fails twice in a row, it **switches back
   automatically**.

If step 2 or 3 fails, the new version **never goes live**.

**When something is wrong after a release:**

```powershell
powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 rollback   # back to the previous colour, seconds
powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 switch     # flip to the other colour (checked first)
powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 status     # what is live, both versions, history
```

**Notes**

- **Once blue-green is installed, the live site's settings are in
  `C:\xampp\deploy\blog\shared\config\secrets.php`**, not in `htdocs\blog\config\secrets.php`.
  - Edit that shared file for live changes (SMTP, Google, `APP_URL`...). Changes apply at once,
    with no deploy needed, because each release's `config\secrets.php` just loads it.
  - `deploy` warns when the development copy has settings the live file is missing.
- **Sessions and uploads are shared**, so students stay signed in and keep their files across
  switches and rollbacks.
- **Preview addresses** (this computer only):
  - `/blog-blue` and `/blog-green` show each version.
  - `/blog-dev` shows the development copy in `htdocs\blog`.
- **Database changes** are shared by both colours, so make them **backward compatible**: add new
  columns and tables first, and remove old ones only in a later release. Both upgrade scripts in
  `sql/` already follow this rule.
- **Restoring a database backup** is manual, because it discards newer data:
  `mysql -u root udom_blog < C:\xampp\deploy\blog\backups\db_....sql`
- **Useful options:**
  - `-NoSwitch` prepares and tests the idle colour without going live; `switch` puts it live later.
  - `-WatchSeconds 60` watches the live site for longer.
  - `-DbPassword ...` is needed once MySQL `root` has a password.
- **`health.php`** returns `200 {"status":"ok"}` or `503`. It can also be used by an uptime monitor.
  Only this computer sees the details and which colour is live.

## Performance settings (server)

These live outside the project folder, so repeat them on every machine or server:

- **Text compression (gzip):** in `C:\xampp\apache\conf\httpd.conf`, remove the `#` in front of
  `LoadModule deflate_module ...` **and** `LoadModule filter_module ...`. `.htaccess` then compresses
  HTML, CSS, JS, JSON, XML and SVG; for example `style.css` drops from 102 KB to 18 KB. File
  downloads (`account/file.php`, `download.php`) are never re-compressed.
- **OPcache:** in `C:\xampp\php\php.ini`, set `zend_extension=opcache`, `opcache.enable=1`,
  `opcache.memory_consumption=128`, `opcache.interned_strings_buffer=16`,
  `opcache.max_accelerated_files=10000`, `opcache.validate_timestamps=1` and
  `opcache.revalidate_freq=2`. Code edits still show up within 2 seconds.
- **Browser caching** is set in `.htaccess`, with no server change needed:
  - CSS and JS are cached for 1 year. They are linked as `style.css?v=<last-modified>`, so an edited
    file is downloaded again straight away.
  - Images are cached for 30 days. Uploads get a new random name when replaced.
  - Favicons and the web manifest are cached for 1 week.
  - PHP pages are never cached.
- **Restart Apache completely** after changing `httpd.conf` or `php.ini`: in the XAMPP Control
  Panel, click **Stop**, then **Start**. A graceful restart does not load new modules.
- Pages are gzip-compressed, so CSRF tokens are masked with fresh random bytes on every page. This
  stops the "BREACH" attack, which could otherwise guess a token from compressed HTTPS responses.

## Google sign-in (v6)

Students can sign up and sign in with **Continue with Google**. Username/password sign-in keeps
working. The button stays hidden until the three `GOOGLE_*` settings are filled in.

**1. Upgrade the database.** Import `sql/upgrade_v6_google_auth.sql` as `root` (safe to run twice).
It adds `users.google_sub`, `users.has_password` and `users.email_verified_at`.

**2. Create the OAuth client** in [Google Cloud Console](https://console.cloud.google.com/):

1. Create (or pick) a project.
2. **Google Auth Platform → Branding** (older consoles: *APIs & Services → OAuth consent screen*):
   - App name `UDOM Student Materials`, a support email, and your contact email.
   - Audience: **External**. While the app is in *Testing*, add the Google accounts that may sign
     in under *Test users*. Choose **Publish app** when you go live.
   - Data access / scopes: `openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`.
     These are non-sensitive, so no Google verification review is needed.
3. **Clients** (older consoles: *APIs & Services → Credentials → Create credentials → OAuth client ID*):
   - Application type: **Web application**.
   - Authorized redirect URI: `http://localhost/blog/account/google_callback.php`
   - Leave *Authorized JavaScript origins* empty (the site uses no Google JavaScript).
4. Copy the **Client ID** and **Client secret**.

**3. Fill in `config/secrets.php`** (never commit this file):

```php
'GOOGLE_CLIENT_ID' => '1234567890-abc.apps.googleusercontent.com',
'GOOGLE_CLIENT_SECRET' => 'GOCSPX-...',
'GOOGLE_REDIRECT_URI' => 'http://localhost/blog/account/google_callback.php',
```

The redirect URI must match the one in Google Cloud **exactly** (scheme, host, folder, no trailing slash).

**How it works**

- **Pages:** `account/google_start.php` → Google → `account/google_callback.php` →
  `account/google_finish.php` (first sign-in only: confirm name and username, accept the Terms).
  The code is in `includes/google_auth.php`.
- **Checks:**
  - `state` blocks forged sign-ins and works only once; `nonce` blocks token replay; PKCE (S256)
    makes a stolen code useless.
  - The ID token's RS256 signature is verified locally against Google's public keys. The keys are
    cached in `logs/google_jwks.json`.
  - `iss`, `aud`, `exp`/`iat` and `nonce` must all match, and `email_verified` must be true.
- **Strict `state` check** (`google_check_callback_state()`), run FIRST on every callback,
  including "cancelled" and error replies. A callback is rejected with 400 unless:
  - this browser session started the sign-in;
  - there is exactly **one** `state` parameter;
  - it is in the exact generated format (43 base64url characters);
  - it matches in a constant-time comparison;
  - it is under 10 minutes old and used only once;
  - the request is a GET;
  - `code` and `error` are not both present;
  - `iss`, if sent, is Google (RFC 9207);
  - for Connect, the same student is still signed in.

  Rejections are logged with a reference (never the values). After 10 rejections from one network
  in 15 minutes, the callback answers 429. The callback page sends `Referrer-Policy: no-referrer`.
- **What is stored:** only Google's account ID (`google_sub`). Access tokens are thrown away and
  never stored or logged.
- **Same email, existing account:** it is **not** linked automatically, because that could let
  someone take over the account. The owner signs in with their password and chooses
  **Password & Security → Connect Google**.
- **Google-only accounts** have no password. They can *set* one under Password & Security. They
  cannot disconnect Google until they do, so they never lock themselves out.
- **Admin:** the admin panel stays password-only. Linked accounts show a *Google* badge in
  *Admin → Users*.
- **Rate limit:** sign-in starts are limited per IP (`google` scope, 20 per 15 minutes).

**Going live**

- Switch to HTTPS first: in `secrets.php` set `FORCE_HTTP => false`, `FORCE_HTTPS => true`, and see
  `.htaccess`. Google only accepts `http://` redirect URIs for `localhost`.
- In Google Cloud, add `https://YOUR-DOMAIN/account/google_callback.php` (adjust the folder) and
  update `GOOGLE_REDIRECT_URI` to match.
- Publish the app on the Branding / consent screen page so that any Google account can sign in, not
  only test users.

## Comment Replies (v4)

- **Upgrading:** import `sql/upgrade_v4_comment_replies.sql` as `root` (safe to run twice).
- Anyone can **reply** to any comment, including replies. Threads indent up to 3 levels; deeper
  replies say "Replying to <name>".
- **Only the author can edit or delete a comment.** This is checked on the server, not just hidden
  in the page.
  - A signed-in student owns their comments on any device.
  - A guest can edit or delete only from the browser session that posted the comment.
  - Edited comments show "(edited)".
  - The admin can delete any comment for moderation, but cannot edit other people's words.
- Deleting a comment that has replies leaves "This comment was deleted", so other people's replies
  stay. The placeholder disappears once its replies are gone.

## Features

- **Landing page:** explains what the site is and how to use it, with live post/comment counts, category cards,
  a "how it works" section, the 3 newest posts and a quick search box.
- **Home page:** hero with search bar, category filter (All, UDOM News, Design, History) with post counts,
  grid of cards (image, category badge, title, excerpt, date, author, Read More), 6 posts per page.
- **Live search:** results update as you type (searches post titles). Search, category filter and pagination all
  update without a page reload, and the URL stays in sync so refresh and shared links show the same results.
  With JavaScript disabled, the same features work through normal page loads.
- **Single post page:** featured image, title, author, date, comment count, full content, share buttons
  (X, Facebook, LinkedIn, WhatsApp, copy link) and a comments section with a validated form.
- **Admin panel:** secure login, dashboard with stats and a post table, create / edit / delete.
- **Image display ratio:** when adding or editing a post the admin picks how its image is framed: Original
  (the image's own shape, read from the file on upload), 16:9, 4:3, 1:1, 4:5, 3:4 or 9:16. The form shows the
  image size, marks the closest ratio as "Best fit" and previews the frame live. Images are never cropped
  (`object-fit: contain`); on the post page the frame is sized so portrait images fit on screen. Stored in
  `posts.image_ratio`, `posts.image_width` and `posts.image_height`.
- **Downloads & documents:** per post, the admin can allow visitors to download the featured image
  ("Download Image" button) and attach up to 10 documents (PDF, DOC, DOCX, PPT, PPTX, 20 MB each). Visitors can
  **View** a document first (`view.php`) or **Download** it (`download.php`, which counts downloads).
  PDFs open in the browser's PDF viewer; DOCX shows text, headings, lists, tables and pictures; PPTX shows each
  slide's title, text and pictures; old DOC/PPT files are download-only. Previews are built in plain PHP (no
  ZipArchive extension, Office or LibreOffice needed).
- **Loading animations:** a navy/gold loading screen on every page (fades out when the page is ready and
  reappears when moving to another page), pulsing placeholder cards while live search, filters and pagination
  load, and spinner buttons on form submit ("Saving...", "Uploading 3 files (12.4 MB)...") that also block
  double submits. Flat colors only; respects the "reduce motion" setting; hides itself if JavaScript fails.
- **Category management (`admin/categories.php`):** the admin can add, rename and delete categories, each with
  an icon and a short description. The header Categories menu, filter buttons, landing page cards, footer and
  post form all read the `categories` table, so changes appear everywhere immediately. Renaming a category
  renames it on all its posts (foreign key `ON UPDATE CASCADE`); deleting a category with posts first asks which
  category to move them to, and the database refuses to delete a category that still has posts.
- **Post form:** title, category dropdown, author, image upload with preview, excerpt with character counter,
  content area with a formatting toolbar (bold, italic, heading, paragraph, lists, quote, link).
  Plain text without HTML is turned into paragraphs automatically.

## Security

- PDO prepared statements for every query that uses input (SQL injection protection).
- **Login hardening:** the username is bound as a typed parameter in a prepared statement (no SQL injection) and
  must match a strict whitelist (letters, digits, `.` `_` `-`) before it reaches the database; 5 failed attempts per
  IP in 15 minutes locks login; CSRF token on the form; same error message and timing for unknown users;
  no credentials shown on the page.
- Passwords stored with `password_hash()` and checked with `password_verify()`; session ID regenerated on login.
- The admin panel is not linked from the public navigation or footer.
- All output escaped with `htmlspecialchars()`; post HTML is filtered to safe formatting tags, with scripts,
  event handlers and `javascript:` links removed.
- CSRF tokens on all admin forms; delete only works via POST with a valid token.
- Image uploads checked by real file content (JPG, PNG, WEBP, GIF only, max 5 MB) and saved under random names.
- `uploads/.htaccess` prevents scripts from running in the uploads folder.
- Category is validated on the server against the allowed list.
- Documents: extension whitelist plus content check (PDF signature, Office file headers, DOCX/PPTX structure);
  macro-enabled Office files rejected; stored under random names in a folder the web server will not serve;
  delivered only by database id through `download.php` with `nosniff`; previews escape all text.

## Design System

| Token      | Value     |
|------------|-----------|
| Primary    | `#0A1E3C` (navy) |
| Accent     | `#D4AF37` (gold) |
| Background | `#F5F5F5` |
| Cards      | `#FFFFFF` |
| Text       | `#111111` |
| Muted      | `#666666` |

Inter / Arial, H1 32px bold, H2 22px, body 16px, 20px padding, 12px radius, soft shadows.
Flat colors only, no gradients, no emojis; all icons are Font Awesome 6.
