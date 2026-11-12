# UDOM Student Materials - Blog Website

A dynamic, database-driven blog for **UDOM Student Materials** (University of Dodoma), built with
Vanilla PHP (PDO), MySQL, HTML5, CSS3, Vanilla JavaScript and Font Awesome 6.

All posts, comments and admin accounts are stored in MySQL, so data persists across refreshes,
browser restarts and server restarts.

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
