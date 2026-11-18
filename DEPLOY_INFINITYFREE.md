# Deploying to InfinityFree

This guide puts UDOM Student Materials online with free hosting from InfinityFree. The site will
be at the root of your address, for example `https://udom-materials.infinityfreeapp.com`.

The computer with XAMPP stays your **development and testing** copy. InfinityFree is the **live**
site.

---

## Before you start: InfinityFree's limits

| Limit | What it means for this site |
|---|---|
| **Files over 10 MB are deleted** (silently) | The package sets `MAX_UPLOAD_MB => 10`, so students can't upload anything bigger. The build leaves out larger files that already exist. |
| **"No file hosting / file sharing" in the Terms of Service** | Student file sharing is part of this site. Keep it for study materials only (small notes, slides, PDFs). If the account gets flagged, move to paid hosting. |
| **Browser check (`aes.js`) on every visit** | Browsers pass automatically. Tools such as uptime monitors or `curl` are blocked, so `health.php` can only be opened in a browser. |
| **Outgoing email only on port 587** | Brevo on port 587 works; the package already uses it. |
| **No shell or PowerShell** | `deploy\deploy.ps1` (blue-green) is for the XAMPP computer only. On InfinityFree you deploy by uploading files (step 6). |

---

## 1. Create the hosting account

1. Sign up at https://infinityfree.com and click **Create Account**.
2. Choose a free subdomain (for example `udom-materials.infinityfreeapp.com`) or connect your own
   domain.
3. Open the account's **Control Panel** (vPanel). Your login details are on the client area page;
   you'll need the **vPanel password** for the database.

## 2. Turn on HTTPS (free SSL)

1. In the client area, open the account, then **SSL Certificates** → request a certificate for
   your domain.
2. Follow the steps until the certificate shows as **Active**. This can take up to an hour.

Wait for this to finish before uploading the site, because the site forces HTTPS.

## 3. Create the database

1. Control Panel → **MySQL Databases** → create a database, for example `udom`.
2. Note the four values shown on that page:

| Shown as | Example | Goes into `secrets.php` as |
|---|---|---|
| MySQL Host Name | `sql123.infinityfree.com` | `DB_HOST` |
| MySQL DB Name | `if0_37000000_udom` | `DB_NAME` |
| MySQL User Name | `if0_37000000` | `DB_USER` |
| Password | your **vPanel** password | `DB_PASS` |

## 4. Build the upload package (on the XAMPP computer)

```powershell
cd C:\xampp\htdocs\blog
C:\xampp\php\php.exe deploy\build_infinityfree.php --app-url=https://udom-materials.infinityfreeapp.com
```

Use **your** address, with `https`, no folder and no slash at the end. The script writes to
`C:\xampp\deploy\infinityfree\`, which is outside the project and outside git:

- `htdocs\`: the site to upload. It is already set up for the domain root, HTTPS through
  InfinityFree's proxy, the 10 MB limit, and admin access by password.
- `database_import.sql`: your posts, users (password hashes only), comments and file records.
  **It contains personal data, so keep it private.**
- `backups\site-<date>.zip`: a copy of this release, for rolling back later.

The script also checks InfinityFree's limits (PHP files under 1 MB, `.htaccess` under 10 kB, other
files under 10 MB) and checks every PHP file for syntax errors.

## 5. Fill in the database settings

Open `C:\xampp\deploy\infinityfree\htdocs\config\secrets.php`. Replace every value that starts
with `FILL_ME` with the values from step 3:

```php
'DB_HOST' => 'sql123.infinityfree.com',
'DB_NAME' => 'if0_37000000_udom',
'DB_USER' => 'if0_37000000',
'DB_PASS' => 'your vPanel password',
```

Everything else is already filled in from your current settings: Brevo email, Google sign-in,
your contact email, `FORCE_HTTPS`, `MAX_UPLOAD_MB` and `ADMIN_ACCESS`. Save the file.

## 6. Import the database and upload the files

**Database**
1. Control Panel → **phpMyAdmin** → click your database (`if0_..._udom`).
2. Open the **Import** tab, choose `C:\xampp\deploy\infinityfree\database_import.sql`, then click
   **Import**.
3. You should see 10 tables.

**Files.** FTP is more reliable than the web file manager for many files.
1. Install **FileZilla**. FTP details are on the client area page: host `ftpupload.net`, port 21,
   your FTP username and password.
2. On the server, open the **`htdocs`** folder and delete InfinityFree's default `index2.html`.
3. Upload **the contents** of `C:\xampp\deploy\infinityfree\htdocs\` into `htdocs`. Upload the
   files inside the folder, not the folder itself. Make sure the hidden `.htaccess` files are
   included: in FileZilla, use *Server → Force showing hidden files*.

## 7. Connect email and Google sign-in to the new address

**Brevo (email)**
- In Brevo, open **Security → Authorized IPs** and **turn off "Block unknown IP addresses"**.
  InfinityFree sends email from shared servers whose IP addresses can change, so a single
  authorized IP stops working.
- After the first email attempt, the IP can be authorized from Brevo's alert email instead, but
  turning the block off is more reliable on InfinityFree.

**Google sign-in**
- In Google Cloud Console → **Google Auth Platform → Clients** → your client, add this **Authorized
  redirect URI**: `https://YOUR-ADDRESS/account/google_callback.php`. It must match
  `GOOGLE_REDIRECT_URI` in `secrets.php` exactly.
- On the **Branding** page, add your InfinityFree domain under *Authorized domains*. Publish the
  app when you want everyone, not only test users, to sign in with Google.

## 8. Check the live site

Open each of these in a **browser**:

| Address | Expected |
|---|---|
| `http://YOUR-ADDRESS/` | redirects to `https://` and shows the landing page |
| `https://YOUR-ADDRESS/health.php` | `{"status": "ok"}` |
| `https://YOUR-ADDRESS/config/secrets.php` | Page not found (the settings are protected) |
| `https://YOUR-ADDRESS/admin/login.php` | admin sign-in |

Then:
1. Create a test student account and upload a small file.
2. Use **Forgot password?** with your own account's email and check the email arrives.
3. Open **Password & Security**. The *Recent sign-ins* list should show **your real IP address**.
   If every sign-in shows the same unfamiliar address instead, set
   `'TRUST_PROXY_IP_HEADER' => 'HTTP_X_FORWARDED_FOR'` in `secrets.php` and upload that file
   again. Without this, failed sign-ins by one person would lock out everyone.

**If the site shows "Too many redirects"**, InfinityFree isn't sending the HTTPS header. In
`htdocs/.htaccess`, put a `#` in front of the two lines under `1. Protocol`, and set
`'TRUST_PROXY_HTTPS' => false` in `secrets.php`.

**If a page shows a 500 error**, read `htdocs/logs/php-errors.log` through FTP.

## 9. Updating the live site later

1. Make and test the changes on the XAMPP computer.
2. Run the build again (step 4). The script asks nothing, and a fresh `secrets.php` is generated
   with `FILL_ME` placeholders.
3. Copy the **database lines** (`DB_*`) from the live `secrets.php` into the new one. To avoid
   this, just skip uploading `config/secrets.php` when nothing in it changed.
4. Upload the changed files.
   - **Never** upload over `uploads/` or `logs/` on the server: they hold the live students' files
     and logs.
   - Upload the database file only for a brand-new site. Importing it again would replace live
     data.
5. **Database changes:** run the matching `sql/upgrade_v*.sql` in phpMyAdmin. First remove its
   first line, `USE udom_blog;`, because the database name is different on InfinityFree.

**Rolling back:** upload the files from the previous `backups\site-<date>.zip`, again leaving
`uploads/`, `logs/` and `config/secrets.php` on the server untouched.

**Backups of live data:** phpMyAdmin → **Export** (database), and download `htdocs/uploads/`
through FTP. Do this regularly, because free hosting has no backup guarantee.
