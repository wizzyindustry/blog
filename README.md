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

Change the password after first login. To set a new one, generate a hash with
`php -r "echo password_hash('NEW_PASSWORD', PASSWORD_DEFAULT);"` and update the `admins` table in phpMyAdmin.

### Database settings

`config/db.php` uses the XAMPP defaults (`root`, empty password, database `udom_blog`). Edit it if your MySQL
setup differs.

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
admin/login.php               Admin login (prepared statement, whitelist, rate limit, CSRF)
admin/logout.php              Ends the admin session
admin/auth_check.php          Guard included by every protected admin page
admin/dashboard.php           Stats and table of all posts with Edit / Delete
admin/create.php              New post form
admin/edit.php?id=            Edit post form
admin/delete.php?id=          Deletes a post (POST + CSRF token only)
admin/categories.php          Add / edit / delete categories (icon, description, move posts on delete)
admin/includes/               Admin header, footer and the shared post form
assets/css/style.css          All styles (flat colors, no gradients, mobile-first)
assets/js/main.js             Live search, filter, pagination, menu, editor toolbar, validation
uploads/                      Post images (.htaccess blocks script execution)
sql/database.sql              Full schema and sample data for phpMyAdmin
```

---

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
