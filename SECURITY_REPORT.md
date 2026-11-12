# Security Hardening Report: UDOM Student Materials

**Date:** 11 November 2026
**Scope:** the whole PHP site (public pages, student accounts, admin panel), its MySQL database, its
front-end libraries and the git repository (`github.com/wizzyindustry/blog`).
**Result:** every requested fix is in place. **73 of 73 automated security tests pass.**
**Three actions are still needed from you.** See [Action required](#action-required).

---

## Action required

| # | Severity | What | Why it matters |
|---|----------|------|----------------|
| 1 | **Critical** | **Purge 4 private student uploads (+ 2 admin documents, 1 image) from git history and GitHub.** | Commit `f7a00ea` contains `uploads/user_files/uf_*`. The GitHub repo is **public**, so anyone can download these files today. I have stopped tracking them, but they remain in the history until it is rewritten and force-pushed, and that needs your approval. |
| 2 | **High** | **Change the admin password.** | The account still uses `admin123`, which is printed in the README. The site now forces a change at the next admin sign-in, but until then the password is guessable (only from this computer, see fix 9). |
| 3 | Medium | **Set a password on the MySQL `root` account** (XAMPP ships with none) and hide server versions in XAMPP's config. | The site no longer uses `root`, but any local program still can. Settings are listed under [Outside the project](#outside-the-project). |

No API keys, tokens or private keys were found anywhere: not in the code, not in the browser
files, and not in either commit of the git history.

---

## What was fixed

### 1. Secrets never reach the browser or git
- **Before:** database credentials were hard-coded in `config/db.php`, which is committed to git.
- **After:**
  - Credentials live in `config/secrets.php`, which is in `.gitignore` and returns 404 if requested
    over the web. Environment variables can override it.
  - `config/secrets.example.php` documents the setup with placeholder values.
  - No JavaScript or CSS file contains any secret (tested).
  - The new `.gitignore` also excludes uploads, avatars, documents and logs.

### 2. Database locked down
- **Least privilege:** the site now connects as a new MySQL account, `udom_app`, with a 48-character
  random password. It can only `SELECT / INSERT / UPDATE / DELETE` rows in `udom_blog`. Tested: it
  cannot `DROP`, `ALTER`, `GRANT`, or read `mysql.user`.
- **Users see only their own data:** MariaDB has no row-level security, so ownership is enforced in
  every query (`WHERE user_id = <signed-in user>`). I tested every endpoint that takes a file ID while
  signed in as a different student:
  - download a private file → 404
  - open its page → 404
  - open its edit page → redirected away
  - make it public → no change
  - delete it → no change

  A guest asking for a private file is sent to the login page. Admin pages refuse students.
- **SQL injection:** every query is a prepared statement with real (non-emulated) prepares, and
  multi-statement queries are now disabled.

### 3. HTTPS everywhere (currently switched OFF: HTTP mode until the site is live)

> **Update, 12 Nov 2026:** at the owner's request the site now runs on **plain HTTP** because it is
> not live yet. `https://` requests are redirected to `http://`. HTTPS responses send
> `Strict-Transport-Security: max-age=0`, so browsers forget the earlier one-year HTTPS rule.
> Without HTTPS the session cookie is automatically named `udom_sid` and loses the `Secure` flag;
> it keeps `HttpOnly` and `SameSite=Lax`. **Before going live:** install a real certificate, set
> `FORCE_HTTP => false` and `FORCE_HTTPS => true` in `config/secrets.php`, and apply the
> "WHEN THE SITE GOES LIVE" lines in `.htaccess`. The HTTPS setup described below then applies again.
- `.htaccess` sends every `http://` request to `https://` with a 301. PHP repeats this check in case
  `mod_rewrite` is missing.
- HSTS (`max-age=31536000`) tells browsers to use HTTPS only for a year. The CSP adds
  `upgrade-insecure-requests`.
- **Note:** locally this uses XAMPP's self-signed certificate (https://localhost/blog). On a real
  server, install a proper certificate such as Let's Encrypt. Before that, `FORCE_HTTPS` can be set
  to `false` in `config/secrets.php`.

### 4. Every form validated on the server
I audited all 21 POST forms.
- **Comment form:** this was the only form missing protection. It now has a CSRF token, a hidden
  bot trap, a rate limit, a check on which characters a name may contain, a cap of 2 links, and
  removal of control characters.
- **All other forms** (register, login, profile, password, upload, file details, visibility,
  delete, every admin form) already checked:
  - text length
  - allowed characters
  - allowed values for every dropdown and filter
  - integer IDs
  - that the signed-in user owns the record
- **Example (tested):** a profile name of `<script>alert(1)</script>` is rejected by the server.

### 5. XSS blocked
- **Content-Security-Policy** on every page:
  - scripts may only come from this site and cdnjs (with integrity hashes)
  - no inline scripts
  - `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`
- **Inline JavaScript removed:** all 16 inline handlers (`onchange`, `onerror`) and the one inline
  `<script>` block were rewritten as `data-` attributes and a JSON data block, so the strict policy
  breaks nothing.
- **Post content:** the old regex filter could be bypassed with encoded payloads such as
  `jav&#x61;script:`. It was replaced by a DOM-based allow-list sanitizer:
  - only safe tags and attributes are kept
  - links and images may only use `http`, `https`, `mailto` or relative URLs
  - `<script>`, `<svg>`, `<iframe>`, `style=` and all `on*=` attributes are removed
  - tested against 10 known XSS payloads, all neutralised
- All other output was already escaped with `htmlspecialchars` (`sanitize()`).

### 6. Rate limiting

| Action | Limit | Tested |
|--------|-------|--------|
| Student login | 5 failures per IP per 15 min **and** 10 failures per account per 15 min (from any IP) | Yes |
| Admin login | the same | Yes |
| Registration | 5 accounts per IP per hour | |
| Comments | 5 per IP per 10 min | Yes |
| Uploads | 30 upload requests per student per hour | |
| Password change / account delete | 5 wrong passwords per 15 min | |
| Live search API | 120 requests per IP per minute (returns HTTP 429) | Yes |

The per-account limit is new: before, an attacker could try passwords forever by rotating IP addresses.

### 7. CSRF protection
- Every POST form carries a per-session token, and the token is renewed at each login.
- **New:** POSTs from another website are refused even if they carry a valid token. The `Origin` /
  `Referer` header must match this site.
- **New:** the comment form and admin logout were the two places without CSRF protection. Admin
  logout was a plain GET link; both now require POST plus a token.

### 8. Session cookies secured

| Setting | Value |
|---------|-------|
| Name | `__Host-udom_sid`. Browsers reject it unless it is Secure, host-only and `Path=/` |
| Flags | `Secure`, `HttpOnly`, `SameSite=Lax` |
| Strict mode | IDs invented by an attacker are replaced (tested) |
| Cookie-only | session IDs never appear in URLs |
| Rotation | a new session ID at login and every 15 minutes |
| Idle timeout | admin 30 min, students 2 h |
| Theft check | a cookie replayed from a different browser is rejected (tested) |

### 9. Upload restrictions
- **Allow-list only:** PDF, Word, PowerPoint, Excel, images, video, audio, ZIP/RAR/7z and plain text.
  - "Any file" was removed.
  - SVG was removed because it can carry scripts.
  - Hard-to-verify formats were dropped (WMV, FLV, AMR, TAR, BZ2, XZ, TIFF).
- **The contents must match the extension:** PDF header, Office file structure (macros refused),
  real image data, real media container, valid UTF-8 text.
- **Refused regardless of name:** programs (`MZ`/ELF), `<?php`, HTML, JavaScript, shell scripts.
  Tested:
  - an `.exe` renamed `.pdf` → refused
  - a PHP web-shell disguised as `.jpg` → refused
  - an `.svg` → refused
  - a `.txt` containing `<script>` → refused
  - a forged `type=any` → refused
  - a genuine PDF → accepted
- **Limits:** 50 MB per file (and php.ini's 40 MB), 10 files per upload, 1 GB per student.
- **Storage:** files are saved with random names and no extension, in folders where web access is
  denied and PHP cannot run.

### 10. Admin area locked
- **This computer only:** `admin/.htaccess` (`Require local`) allows only the computer running XAMPP;
  everyone else gets 403. To manage from another device, add `Require ip <address>` in that file.
- **Default password:** forces a password change (12+ characters, mixed case, a number, a symbol, not
  containing "admin"). Every admin page redirects there until it is done (tested).
- **New Change Password page** in the admin sidebar and profile menu.
- **Session:** 30-minute idle sign-out, POST-only logout, lockout after failed logins.
- **Headers:** `Cache-Control: no-store` and `noindex` on all admin pages.

### 11. Debug mode off
- `display_errors` is off in both `.htaccess` and PHP. Errors go to `logs/php-errors.log`, which the
  web cannot open.
- A global handler shows visitors a plain "Something went wrong" page with no file paths.
- The database connection error no longer prints MySQL details. Tested with a wrong password: the
  visitor sees "temporarily unavailable", and the log has the cause.
- The `X-Powered-By: PHP/8.2.12` header was removed.
- Tested: malformed input such as `?id[]=1` produces no warnings or paths.

### 12. Packages
There is no npm or Composer; the site loads 4 libraries from CDNs, and all 4 are used.

| Package | Before | After | Notes |
|---------|--------|-------|-------|
| Chart.js | 4.4.1, **no integrity hash** | **4.5.0 + SRI** | Admin dashboard charts. The hash was verified against the file actually served. |
| Font Awesome | 6.5.2 + SRI | **6.7.2 + SRI** | Latest 6.x; same icon names. 7.x exists but renames some styles. |
| Inter font | Google Fonts | unchanged | Google does not support SRI for font CSS; allowed only through `style-src`/`font-src`. |

Neither old version had a known vulnerability. Upgrading Chart.js also adds tamper protection (SRI),
so a compromised CDN can no longer inject code. **Removed:** ~1.4 KB of CSS for the old admin
top-navigation bar, which the sidebar replaced.

### 13. Git history scan
- **Method:** I searched both commits' full diffs for AWS, Stripe, GitHub, Google and Slack key
  formats, private keys, and `api_key=` / `secret=` / `token=` assignments.
- **Keys:** none found, so nothing needed replacing. The only credential ever committed was
  `root` with an empty password (XAMPP's default). It is no longer used and is replaced by the
  `udom_app` account.
- **User data:** private uploads were found in the history; see Action required #1. They are now
  untracked and ignored.

---

## Test results (73/73 passed)

| Area | Checks |
|------|--------|
| HTTPS and headers (redirect, HSTS, CSP, frame, nosniff, no version leak, static files) | 8 |
| Session cookie (prefix, Secure, HttpOnly, SameSite, strict mode) | 5 |
| Secrets, internal files and debug (14 blocked paths, no secrets in JS/CSS, no PHP warnings, DB error hidden) | 19 |
| Least-privilege DB account (can read; cannot DROP / ALTER / GRANT / read mysql.user) | 5 |
| CSRF (missing token, cross-site Origin, comment form) | 3 |
| XSS (10-payload sanitizer, server-side name check, no inline handlers) | 3 |
| Upload restrictions (5 attack files refused, genuine PDF accepted) | 6 |
| Data isolation / IDOR (8 cross-account attempts, stolen cookie) | 9 |
| Rate limiting (user IP, user account, admin, comments, search) | 6 |
| Admin lock (GET logout, forced password change on every page, IP rule, no-store) | 5 |
| Registration of test users, git history and tracking | 4 |

I also loaded the public pages in headless Edge over HTTPS: there were **no CSP violations**, and
icons, fonts and images load normally. The test accounts and test data were deleted afterwards. The
test script can be re-run at any time.

---

## Outside the project

These are XAMPP-wide settings, so I did not change them:

- **MySQL root password:** in phpMyAdmin, go to *User accounts → root → Change password*. Then update
  `C:\xampp\phpMyAdmin\config.inc.php`.
- **Hide server versions:** responses still say `Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12`.
  - In `C:\xampp\apache\conf\httpd.conf`, add `ServerTokens Prod` and `ServerSignature Off`.
  - In `C:\xampp\php\php.ini`, set `expose_php=Off` and `display_errors=Off`.
  - Then restart Apache.
- **Virus scanning:** uploads are checked by type and structure, not by antivirus. ZIP/RAR/7z
  archives could still contain malware. For a public deployment, add ClamAV or keep archives private.

## Files changed

**New:**
- `includes/security.php`
- `config/secrets.php` (git-ignored) and `config/secrets.example.php`
- `.gitignore`
- `admin/.htaccess`, `uploads/avatars/.htaccess`
- `admin/password.php`
- `SECURITY_REPORT.md`

**Updated:**
- `config/db.php`, `.htaccess`, `uploads/.htaccess`
- `includes/functions.php`, `includes/users.php`, `includes/header.php`
- `post.php`, `search-api.php`
- `account/login.php`, `register.php`, `upload.php`, `security.php`, `files.php`, `includes/*`
- `admin/login.php`, `logout.php`, `auth_check.php`, `dashboard.php`, `includes/*`, and the list pages
- `assets/js/main.js`, `assets/css/style.css`
- `README.md`
