<?php
/**
 * Builds the upload package for InfinityFree (free shared hosting).
 *
 *   C:\xampp\php\php.exe deploy\build_infinityfree.php [--app-url=https://yoursite.infinityfreeapp.com]
 *                                                      [--out=C:\xampp\deploy\infinityfree]
 *
 * Output (outside the project and outside git):
 *   <out>\htdocs\              -> upload the CONTENTS of this folder into InfinityFree's "htdocs"
 *   <out>\database_import.sql  -> import in InfinityFree's phpMyAdmin
 *   <out>\backups\site-<time>.zip  copy of this release (to roll back: upload the previous one)
 *
 * What it changes for InfinityFree:
 *   - site at the domain root (error pages use /error.php instead of /blog/error.php)
 *   - HTTPS forced the way InfinityFree's front proxy needs (X-Forwarded-Proto), HSTS on
 *   - config/secrets.php for production: your SMTP, Google and contact settings + FILL_ME
 *     placeholders for the InfinityFree database, FORCE_HTTPS, 10 MB upload limit, admin access
 *   - no docs, SQL scripts, deploy tools, logs or git files in the package
 *   - checks InfinityFree's hard limits: PHP/JS/HTML <= 1 MB, .htaccess <= 10 kB, other files <= 10 MB
 */
if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

$root = str_replace('\\', '/', dirname(__DIR__));
$opts = getopt('', ['out::', 'app-url::', 'secrets::', 'db::', 'mysqldump::']);
$out = rtrim(str_replace('\\', '/', $opts['out'] ?? 'C:/xampp/deploy/infinityfree'), '/');
$appUrl = rtrim((string) ($opts['app-url'] ?? ''), '/');
$secretsSource = $opts['secrets'] ?? (is_file('C:/xampp/deploy/blog/shared/config/secrets.php')
    ? 'C:/xampp/deploy/blog/shared/config/secrets.php' : $root . '/config/secrets.php');
$dbName = $opts['db'] ?? 'udom_blog';
$mysqldump = $opts['mysqldump'] ?? 'C:/xampp/mysql/bin/mysqldump.exe';

const LIMIT_CODE = 1024 * 1024;        // InfinityFree: HTML, PHP and JS files
const LIMIT_HTACCESS = 10 * 1024;      // .htaccess files
const LIMIT_OTHER = 10 * 1024 * 1024;  // everything else (bigger files are deleted by the host)

$problems = [];
$warnings = [];
function say(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): void { fwrite(STDERR, "BUILD FAILED: $s" . PHP_EOL); exit(1); }

if ($appUrl !== '' && !preg_match('#^https://[a-z0-9.-]+$#i', $appUrl)) {
    fail('--app-url must look like https://yoursite.infinityfreeapp.com (https, no folder, no trailing slash)');
}
if (strpos(str_replace('\\', '/', $out) . '/', $root . '/') === 0) {
    fail('--out must be OUTSIDE the project folder (it would be served by the local web server)');
}

// ------------------------------------------------------------------ 1. clean output folder
$site = $out . '/htdocs';
if (is_dir($site)) {
    if (!is_file($out . '/.infinityfree-build')) {
        fail("$out exists but was not made by this script; refusing to delete it");
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($site);
}
@mkdir($site, 0755, true);
@mkdir($out . '/backups', 0755, true);
file_put_contents($out . '/.infinityfree-build', "Made by deploy/build_infinityfree.php\n");

// ------------------------------------------------------------------ 2. copy files
$skipTop = ['.git', '.github', '.vscode', '.idea', '.claude', 'deploy', 'sql', 'logs', 'node_modules', 'dist'];
$skipFiles = ['config/secrets.php', 'RELEASE.json', '.gitignore', '.gitattributes', '.editorconfig'];
$skipExt = ['md', 'log', 'bak', 'tmp', 'swp'];
$count = 0;
$bytes = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $file) {
    $rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');
    $top = explode('/', $rel)[0];
    if (in_array($top, $skipTop, true) || in_array($rel, $skipFiles, true)) {
        continue;
    }
    if ($file->isLink() || preg_match('#(^|/)(Thumbs\.db|desktop\.ini|\.DS_Store)$#i', $rel)) {
        continue;
    }
    if ($file->isDir()) {
        @mkdir($site . '/' . $rel, 0755, true);
        continue;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (in_array($ext, $skipExt, true)) {
        continue;
    }
    $size = $file->getSize();
    $name = basename($rel);
    if ($name === '.htaccess' && $size > LIMIT_HTACCESS) {
        $problems[] = "$rel is " . $size . ' bytes (InfinityFree limit for .htaccess: 10 kB)';
    } elseif (in_array($ext, ['php', 'js', 'html', 'htm'], true) && $size > LIMIT_CODE) {
        $problems[] = "$rel is " . round($size / 1048576, 1) . ' MB (InfinityFree limit for PHP/JS/HTML: 1 MB)';
    } elseif ($size > LIMIT_OTHER) {
        $warnings[] = "SKIPPED $rel (" . round($size / 1048576, 1) . ' MB): InfinityFree deletes files over 10 MB';
        continue;
    }
    @mkdir(dirname($site . '/' . $rel), 0755, true);
    copy($file->getPathname(), $site . '/' . $rel);
    $count++;
    $bytes += $size;
}
// Empty, protected logs folder (the site writes its error log here)
@mkdir($site . '/logs', 0755, true);
file_put_contents($site . '/logs/.htaccess', "Require all denied\n");
say("Copied $count files (" . round($bytes / 1048576, 1) . ' MB)');

// ------------------------------------------------------------------ 3. production .htaccess
$ht = file_get_contents($site . '/.htaccess');
$replace = function (string $what, string $from, string $to, int $expected) use (&$ht) {
    $n = substr_count($ht, $from);
    if ($n !== $expected) {
        fail(".htaccess: expected $expected x '$what', found $n (was .htaccess changed? update this script)");
    }
    $ht = str_replace($from, $to, $ht);
};
// Site at the domain root
$replace('ErrorDocument /blog/error.php', ' /blog/error.php', ' /error.php', 11);
// HTTPS: InfinityFree ends HTTPS at its front proxy, which tells us the visitor's scheme in
// X-Forwarded-Proto. Redirect ONLY when it says "http" (checking %{HTTPS} here causes endless loops).
$replace('HTTP-mode redirect',
    "    # ---- 1. Protocol: HTTP for now (the site is not live yet) ----\n"
    . "    # Every HTTPS request is sent back to plain HTTP. 302 = temporary, so browsers do not remember it.\n"
    . "    RewriteCond %{HTTPS} =on\n    RewriteRule ^ http://%{HTTP_HOST}%{REQUEST_URI} [R=302,L]",
    "    # ---- 1. Protocol: HTTPS only (InfinityFree) ----\n"
    . "    # InfinityFree ends HTTPS at its front proxy and reports the visitor's scheme in X-Forwarded-Proto.\n"
    . "    # (Checking %{HTTPS} here would loop forever.) \"Too many redirects\"? Put # before the next 2 lines\n"
    . "    # and set TRUST_PROXY_HTTPS => false in config/secrets.php (see DEPLOY_INFINITYFREE.md).\n"
    . "    RewriteCond %{HTTP:X-Forwarded-Proto} =http\n    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]", 1);
$replace('go-live instructions (already applied)',
    "    # WHEN THE SITE GOES LIVE (with a real certificate): delete the 2 lines above, uncomment the\n"
    . "    # 2 lines below, and set FORCE_HTTP => false / FORCE_HTTPS => true in config/secrets.php.\n"
    . "    # RewriteCond %{HTTPS} !=on\n"
    . "    # RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n\n", '', 1);
$replace('HSTS max-age=0',
    "Header always set Strict-Transport-Security \"max-age=0\" \"expr=%{HTTPS} == 'on'\"",
    "Header always set Strict-Transport-Security \"max-age=31536000\" \"expr=%{HTTP:X-Forwarded-Proto} == 'https'\"", 1);
$ht = "# PRODUCTION .htaccess for InfinityFree - generated by deploy/build_infinityfree.php on " . date('Y-m-d H:i') . "\n" . $ht;
file_put_contents($site . '/.htaccess', $ht);
if (strlen($ht) > LIMIT_HTACCESS) {
    $problems[] = '.htaccess is ' . strlen($ht) . ' bytes after changes (limit 10 kB)';
}
say('.htaccess: site at domain root, HTTPS forced via X-Forwarded-Proto, HSTS 1 year');

// ------------------------------------------------------------------ 4. production secrets.php
$current = is_file($secretsSource) ? (array) require $secretsSource : [];
$url = $appUrl !== '' ? $appUrl : 'https://FILL_ME.infinityfreeapp.com';
$keep = fn(string $k, $default = '') => $current[$k] ?? $default;
$settings = [
    '// Database: from the InfinityFree control panel -> "MySQL Databases" (NOT your local XAMPP values)' => null,
    'DB_HOST' => 'FILL_ME_sqlXXX.infinityfree.com',
    'DB_NAME' => 'FILL_ME_if0_XXXXXXXX_udom',
    'DB_USER' => 'FILL_ME_if0_XXXXXXXX',
    'DB_PASS' => 'FILL_ME_your_vPanel_password',
    '// Public address (https, no trailing slash). Links in emails, sitemap and Google sign-in use it.' => null,
    'APP_URL' => $url,
    'FORCE_HTTP' => false,
    'FORCE_HTTPS' => true,
    '// InfinityFree ends HTTPS at its front proxy, which reports it in X-Forwarded-Proto' => null,
    'TRUST_PROXY_HTTPS' => true,
    '// Leave empty unless every sign-in shows the same IP under Password & Security (see the guide)' => null,
    'TRUST_PROXY_IP_HEADER' => '',
    '// InfinityFree deletes files over 10 MB, so uploads are limited to 10 MB' => null,
    'MAX_UPLOAD_MB' => 10,
    "// Admin panel: 'any' = anyone with the admin password; or your IP addresses, e.g. '41.59.10.20'" => null,
    'ADMIN_ACCESS' => 'any',
    '// Contact address shown on the Privacy, Terms, Cookie and FAQ pages' => null,
    'CONTACT_EMAIL' => $keep('CONTACT_EMAIL'),
    '// Email (Brevo). In Brevo, Security -> Authorized IPs must allow InfinityFree (see the guide)' => null,
    'SMTP_HOST' => $keep('SMTP_HOST'),
    'SMTP_PORT' => 587, // InfinityFree only allows outgoing SMTP on port 587
    'SMTP_SECURE' => 'tls',
    'SMTP_USER' => $keep('SMTP_USER'),
    'SMTP_PASS' => $keep('SMTP_PASS'),
    'MAIL_FROM' => $keep('MAIL_FROM'),
    'MAIL_FROM_NAME' => $keep('MAIL_FROM_NAME', 'UDOM Student Materials'),
    '// Google sign-in: add the redirect URI below to your OAuth client in Google Cloud Console' => null,
    'GOOGLE_CLIENT_ID' => $keep('GOOGLE_CLIENT_ID'),
    'GOOGLE_CLIENT_SECRET' => $keep('GOOGLE_CLIENT_SECRET'),
    'GOOGLE_REDIRECT_URI' => $url . '/account/google_callback.php',
];
$php = "<?php\n/**\n * PRODUCTION settings for InfinityFree - generated by deploy/build_infinityfree.php on " . date('Y-m-d H:i') . ".\n"
    . " * Replace every FILL_ME value before uploading. Never commit this file or share it.\n */\nreturn [\n";
foreach ($settings as $k => $v) {
    $php .= $v === null && strpos($k, '//') === 0 ? "\n    $k\n" : '    ' . var_export($k, true) . ' => ' . var_export($v, true) . ",\n";
}
$php .= "];\n";
file_put_contents($site . '/config/secrets.php', $php);
$fill = substr_count($php, 'FILL_ME');
say("config/secrets.php: production settings written ($fill FILL_ME value(s) to replace)");

// ------------------------------------------------------------------ 5. database export
$sqlFile = $out . '/database_import.sql';
$base = escapeshellarg($mysqldump) . ' --user=root --single-transaction --skip-lock-tables --skip-add-locks --no-tablespaces'
    . ' --skip-triggers --default-character-set=utf8mb4 --skip-comments';
$transient = ['login_attempts', 'password_resets']; // rate-limit counters and reset links: structure only
$data = shell_exec($base . ' ' . escapeshellarg($dbName) . ' '
    . implode(' ', array_map(fn($t) => '--ignore-table=' . escapeshellarg("$dbName.$t"), $transient)) . ' 2>&1');
$schema = shell_exec($base . ' --no-data ' . escapeshellarg($dbName) . ' ' . implode(' ', array_map('escapeshellarg', $transient)) . ' 2>&1');
if (!is_string($data) || strpos($data, 'CREATE TABLE') === false || !is_string($schema) || strpos($schema, 'CREATE TABLE') === false) {
    fail('mysqldump failed: ' . substr((string) $data, 0, 300));
}
// Portable for MySQL and MariaDB hosts: no DEFINERs, no MariaDB sandbox line, no database name
$clean = function (string $sql): string {
    $sql = preg_replace('#^/\*M?!999999\\\\- enable the sandbox mode \*/\R#m', '', $sql);
    $sql = preg_replace('#/\*!\d+ DEFINER=[^*]*\*/#', '', $sql);
    return preg_replace('#^(USE|CREATE DATABASE) .*\R#m', '', $sql);
};
$header = "-- UDOM Student Materials: database for InfinityFree (" . date('Y-m-d H:i') . ")\n"
    . "-- Import in phpMyAdmin INTO THE EMPTY DATABASE you created in the InfinityFree control panel.\n"
    . "-- Contains your posts, users (hashed passwords only), comments and file records.\n"
    . "-- PRIVATE: do not commit, email or share this file.\n\n";
file_put_contents($sqlFile, $header . $clean($data) . "\n-- Structure only (temporary security data)\n" . $clean($schema));
preg_match_all('/^CREATE TABLE `([^`]+)`/m', file_get_contents($sqlFile), $m);
say('database_import.sql: ' . count($m[1]) . ' tables (' . round(filesize($sqlFile) / 1024) . ' KB): ' . implode(', ', $m[1]));

// ------------------------------------------------------------------ 6. checks, backup zip
$lint = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) === 'php') {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($f->getPathname()) . ' 2>&1', $o, $code);
        if ($code !== 0) {
            $problems[] = 'PHP syntax error in ' . substr($f->getPathname(), strlen($site) + 1);
        }
        $lint++;
    }
}
say("PHP syntax: $lint files checked");

foreach ($warnings as $w) {
    say("WARNING  $w");
}
if ($problems) {
    foreach ($problems as $p) {
        fwrite(STDERR, "PROBLEM  $p" . PHP_EOL);
    }
    fail(count($problems) . ' problem(s) - fix them before uploading');
}

$zip = $out . '/backups/site-' . date('Ymd-His') . '.zip';
// Windows' own tar (bsdtar) can write .zip; the GNU tar that Git installs cannot
$tar = is_file('C:/Windows/System32/tar.exe') ? 'C:/Windows/System32/tar.exe' : 'tar';
exec(escapeshellarg($tar) . ' -a -c -f ' . escapeshellarg($zip) . ' -C ' . escapeshellarg($site) . ' . 2>&1', $o, $code);
say($code === 0 && is_file($zip) ? 'Backup of this release: ' . $zip : 'NOTE: could not create the backup zip (tar not available)');

say('');
say('READY: ' . $out);
say('Next: follow DEPLOY_INFINITYFREE.md (fill in FILL_ME values, import the SQL, upload htdocs).');
