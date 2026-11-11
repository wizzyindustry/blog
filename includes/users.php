<?php
/**
 * User Accounts, User Uploads & Activity Tracking
 * UDOM Student Materials Blog
 *
 * Loaded automatically by config/db.php.
 *
 * - Registration / login helpers (sessions, brute-force limits shared with admin login)
 * - "Active user" tracking (users.last_seen_at, updated at most once a minute)
 * - Uploads of ANY file type. Files are stored in uploads/user_files/ under a
 *   random name with NO extension, so nothing uploaded can ever be executed by
 *   the web server; they are delivered only by account/file.php.
 * - Small shared UI helpers (avatars, pagination for long tables)
 */

/* --------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------ */

const USER_MAX_FILE_BYTES = 100 * 1024 * 1024;   // Per file (also capped by php.ini, see user_max_file_bytes())
const USER_STORAGE_QUOTA = 1024 * 1024 * 1024;   // 1 GB per user
const USER_MAX_FILES_PER_UPLOAD = 10;
const USER_MAX_AVATAR_BYTES = 2 * 1024 * 1024;
const USER_ONLINE_MINUTES = 5;                   // "Online now" = seen in the last 5 minutes
const USER_SEEN_UPDATE_SECONDS = 60;             // Write last_seen_at at most once a minute
const LOGIN_MAX_ATTEMPTS_PER_IP = 5;
const LOGIN_LOCK_MINUTES = 15;
const ADMIN_ROWS_PER_PAGE = 25;
const USER_FILES_PER_PAGE = 20;

// File groups shown in filters and charts: kind => [label, icon]
const FILE_KINDS = [
    'image' => ['Images', 'fa-file-image'],
    'document' => ['Documents', 'fa-file-lines'],
    'video' => ['Videos', 'fa-file-video'],
    'audio' => ['Audio', 'fa-file-audio'],
    'archive' => ['Archives', 'fa-file-zipper'],
    'code' => ['Code', 'fa-file-code'],
    'other' => ['Other', 'fa-file'],
];

const FILE_KIND_EXTENSIONS = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'tif', 'tiff', 'heic', 'heif', 'avif', 'psd', 'ai', 'eps', 'raw', 'cr2', 'nef'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'md', 'csv', 'epub', 'pages', 'numbers', 'key', 'tex'],
    'video' => ['mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'm4v', '3gp', 'mpeg', 'mpg'],
    'audio' => ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'wma', 'opus', 'amr'],
    'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'iso'],
    'code' => ['php', 'html', 'htm', 'css', 'js', 'ts', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'h', 'cs', 'go', 'rb', 'rs', 'kt', 'swift', 'sql', 'sh', 'bat', 'ps1', 'yml', 'yaml', 'ipynb', 'm', 'r', 'dart', 'vue'],
];

// What the user says they are uploading. Restricts the file picker (accept) and is checked
// again on the server. key => [label, icon, allowed extensions (null = anything)]
const UPLOAD_TYPES = [
    'any' => ['Any file', 'fa-file', null],
    'pdf' => ['PDF', 'fa-file-pdf', ['pdf']],
    'word' => ['Word', 'fa-file-word', ['doc', 'docx', 'odt', 'rtf']],
    'powerpoint' => ['PowerPoint', 'fa-file-powerpoint', ['ppt', 'pptx', 'odp', 'pps', 'ppsx']],
    'excel' => ['Excel', 'fa-file-excel', ['xls', 'xlsx', 'ods', 'csv']],
    'image' => ['Images', 'fa-file-image', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif', 'avif', 'tif', 'tiff']],
    'video' => ['Videos', 'fa-file-video', ['mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'm4v', '3gp', 'mpeg', 'mpg']],
    'audio' => ['Audio', 'fa-file-audio', ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'wma', 'opus', 'amr']],
    'archive' => ['ZIP / Archives', 'fa-file-zipper', ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz']],
    'text' => ['Notes / Text', 'fa-file-lines', ['txt', 'md']],
];

const SHARED_FILES_PER_PAGE = 12;

// Types that may be shown INSIDE the browser by account/file.php.
// Everything else (including SVG and HTML, which can carry scripts) is always downloaded.
const INLINE_MIME_TYPES = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'bmp' => 'image/bmp', 'avif' => 'image/avif',
    'pdf' => 'application/pdf',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'm4v' => 'video/mp4',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
    'm4a' => 'audio/mp4', 'opus' => 'audio/ogg',
    'txt' => 'text/plain', 'csv' => 'text/plain', 'md' => 'text/plain',
];

/* --------------------------------------------------------------
 * Paths & small helpers
 * ------------------------------------------------------------ */

function user_files_dir(): string
{
    return __DIR__ . '/../uploads/user_files/';
}

function avatars_dir(): string
{
    return __DIR__ . '/../uploads/avatars/';
}

/** Largest single file the server will actually accept (php.ini can be lower than our setting). */
function user_max_file_bytes(): int
{
    $limits = [USER_MAX_FILE_BYTES];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $value = ini_bytes($key);
        if ($value > 0) {
            $limits[] = $value;
        }
    }
    return min($limits);
}

/** Group a file extension into image / document / video / audio / archive / code / other. */
function file_kind_for(string $ext): string
{
    foreach (FILE_KIND_EXTENSIONS as $kind => $list) {
        if (in_array($ext, $list, true)) {
            return $kind;
        }
    }
    return 'other';
}

function file_kind_icon(string $kind): string
{
    return FILE_KINDS[$kind][1] ?? 'fa-file';
}

function file_kind_label(string $kind): string
{
    return FILE_KINDS[$kind][0] ?? 'Other';
}

/** True when account/file.php may show this file in the browser. */
function user_file_inline(array $file): bool
{
    return isset(INLINE_MIME_TYPES[$file['extension']]);
}

/** True when the file is a raster image we can show as a thumbnail. */
function user_file_is_image(array $file): bool
{
    return $file['file_kind'] === 'image' && isset(INLINE_MIME_TYPES[$file['extension']])
        && strpos(INLINE_MIME_TYPES[$file['extension']], 'image/') === 0;
}

/** Two-letter initials for an avatar placeholder, e.g. "Neema Joseph" -> "NJ". */
function user_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'U';
}

/** Avatar markup: the uploaded photo, or coloured initials. $prefix = '../' from sub-folders. */
function user_avatar_html(array $user, string $prefix = '', string $class = 'avatar'): string
{
    if (!empty($user['avatar']) && is_file(avatars_dir() . basename($user['avatar']))) {
        return '<img class="' . $class . '" src="' . $prefix . 'uploads/avatars/' . rawurlencode(basename($user['avatar']))
            . '" alt="' . sanitize($user['full_name']) . '">';
    }
    return '<span class="' . $class . ' avatar-initials" aria-hidden="true">' . sanitize(user_initials($user['full_name'])) . '</span>';
}

/** "3 minutes ago" style label. */
function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'Never';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'Just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $seconds => $unit) {
        if ($diff >= $seconds) {
            $n = (int) floor($diff / $seconds);
            return $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago';
        }
    }
    return 'Just now';
}

/** True when the user was seen within USER_ONLINE_MINUTES. */
function user_is_online(?string $last_seen): bool
{
    return $last_seen !== null && strtotime($last_seen) >= time() - USER_ONLINE_MINUTES * 60;
}

/* --------------------------------------------------------------
 * Brute-force protection (shared by admin and user login)
 * ------------------------------------------------------------ */

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Failed logins from this IP in the lock window, for 'admin' or 'user'. */
function recent_failed_logins(PDO $pdo, string $scope, string $ip): int
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE scope = :scope AND ip_address = :ip
               AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE)'
        );
        $stmt->execute([':scope' => $scope, ':ip' => $ip]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0; // Table missing or not upgraded yet: rate limiting is skipped
    }
}

function record_failed_login(PDO $pdo, string $scope, string $ip, string $username): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (scope, ip_address, username) VALUES (:scope, :ip, :username)');
        $stmt->execute([':scope' => $scope, ':ip' => $ip, ':username' => mb_substr($username, 0, 100)]);
    } catch (PDOException $e) {
        // Ignore
    }
}

/** Clear this IP's failures after a successful login and prune rows older than a day. */
function clear_failed_logins(PDO $pdo, string $scope, string $ip): void
{
    try {
        $stmt = $pdo->prepare(
            'DELETE FROM login_attempts
             WHERE (scope = :scope AND ip_address = :ip) OR attempted_at < (NOW() - INTERVAL 1 DAY)'
        );
        $stmt->execute([':scope' => $scope, ':ip' => $ip]);
    } catch (PDOException $e) {
        // Ignore
    }
}

/* --------------------------------------------------------------
 * Sessions: current user & activity
 * ------------------------------------------------------------ */

/**
 * The logged-in user's row, or null. Loaded once per request.
 * A blocked or deleted account is logged out automatically.
 */
function current_user(bool $refresh = false): ?array
{
    global $pdo;
    static $user = false;

    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if ($user === false || $refresh) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;

        if (!$user || $user['status'] !== 'active') {
            log_out_user();
            $user = null;
        }
    }
    return $user;
}

/** Mark the current user as active (at most one UPDATE per minute per session). */
function touch_user_activity(): void
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $now = time();
    if (($now - (int) ($_SESSION['user_seen_at'] ?? 0)) < USER_SEEN_UPDATE_SECONDS) {
        return;
    }
    $_SESSION['user_seen_at'] = $now;
    try {
        $stmt = $pdo->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Activity tracking must never break a page
    }
}

/** Start a logged-in session for $user and record the login. */
function log_in_user(PDO $pdo, array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_seen_at'] = time();
    unset($_SESSION['csrf_token']);
    csrf_token();

    $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = :id')
        ->execute([':id' => (int) $user['id']]);

    $log = $pdo->prepare('INSERT INTO user_logins (user_id, ip_address, user_agent) VALUES (:uid, :ip, :ua)');
    $log->execute([
        ':uid' => (int) $user['id'],
        ':ip' => client_ip(),
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

/** End the user session (an admin session in the same browser is kept). */
function log_out_user(): void
{
    start_session();
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_seen_at']);
    session_regenerate_id(true);
}

/* --------------------------------------------------------------
 * Validation
 * ------------------------------------------------------------ */

/**
 * Validate profile fields from $_POST. Returns [clean values, errors].
 * $exclude_id: the user's own id when editing (so their own username/email is not "taken").
 */
function validate_user_profile(PDO $pdo, int $exclude_id = 0): array
{
    $data = [
        'full_name' => trim(preg_replace('/\s+/u', ' ', input_text($_POST, 'full_name'))),
        'username' => trim(input_text($_POST, 'username')),
        'email' => mb_strtolower(trim(input_text($_POST, 'email'))),
        'bio' => trim(input_text($_POST, 'bio')),
    ];
    $errors = [];

    if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 120) {
        $errors['full_name'] = 'Full name must be 3 to 120 characters.';
    } elseif (!preg_match("/^[\p{L}\p{M}' .-]+$/u", $data['full_name'])) {
        $errors['full_name'] = 'Full name may only contain letters, spaces, apostrophes, dots and hyphens.';
    }

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $data['username'])) {
        $errors['username'] = 'Username must be 3 to 50 letters, numbers, dots, underscores or hyphens.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (mb_strlen($data['bio']) > 300) {
        $errors['bio'] = 'Bio must be 300 characters or less.';
    }

    if (!isset($errors['username']) || !isset($errors['email'])) {
        $stmt = $pdo->prepare(
            'SELECT username, email FROM users WHERE (username = :u OR email = :e) AND id <> :id LIMIT 2'
        );
        $stmt->execute([':u' => $data['username'], ':e' => $data['email'], ':id' => $exclude_id]);
        foreach ($stmt->fetchAll() as $row) {
            if (strcasecmp($row['username'], $data['username']) === 0 && !isset($errors['username'])) {
                $errors['username'] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $data['email']) === 0 && !isset($errors['email'])) {
                $errors['email'] = 'An account with that email already exists.';
            }
        }
    }

    return [$data, $errors];
}

/** Password rules. Returns an error message or null. */
function password_problem(string $password, string $confirm): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 255) {
        return 'Password is too long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    if (!hash_equals($password, $confirm)) {
        return 'The two passwords do not match.';
    }
    return null;
}

/* --------------------------------------------------------------
 * Uploads
 * ------------------------------------------------------------ */

/** Make sure a protected storage folder exists and denies direct web access. */
function ensure_private_dir(string $dir): bool
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }
    if (!is_file($dir . '.htaccess')) {
        @file_put_contents($dir . '.htaccess', "# Files are delivered only through PHP with safe headers.\nRequire all denied\n");
    }
    return true;
}

/** Value for <input accept="...">, e.g. ".pdf" or ".ppt,.pptx". Empty = any file. */
function upload_type_accept(string $type): string
{
    $exts = UPLOAD_TYPES[$type][2] ?? null;
    return $exts ? '.' . implode(',.', $exts) : '';
}

/** Upload type key that matches a file extension (for filters), or 'any'. */
function upload_type_for_extension(string $ext): string
{
    foreach (UPLOAD_TYPES as $key => [, , $exts]) {
        if ($exts && in_array($ext, $exts, true)) {
            return $key;
        }
    }
    return 'any';
}

/** Icon for a file: specific (PDF, Word...) when known, otherwise by kind. */
function user_file_icon(array $file): string
{
    $type = upload_type_for_extension((string) $file['extension']);
    return $type !== 'any' ? UPLOAD_TYPES[$type][1] : file_kind_icon($file['file_kind']);
}

/** Title shown to people: the chosen title, or the file name without its extension. */
function user_file_title(array $file): string
{
    if (!empty($file['title'])) {
        return $file['title'];
    }
    $name = (string) $file['original_name'];
    $dot = strrpos($name, '.');
    return $dot > 0 ? substr($name, 0, $dot) : $name;
}

/**
 * Read and validate the sharing fields of the upload / edit form.
 * Returns [values, errors]: title, description, visibility, category.
 */
function validate_file_details(): array
{
    $data = [
        'title' => trim(preg_replace('/\s+/u', ' ', input_text($_POST, 'title'))),
        'description' => trim(input_text($_POST, 'description')),
        'visibility' => input_text($_POST, 'visibility') === 'public' ? 'public' : 'private',
        'category' => normalize_category(input_text($_POST, 'category')),
    ];
    $errors = [];
    if (mb_strlen($data['title']) > 200) {
        $errors[] = 'Title must be 200 characters or less.';
    }
    if (mb_strlen($data['description']) > 300) {
        $errors[] = 'Description must be 300 characters or less.';
    }
    return [$data, $errors];
}

/**
 * Save the files chosen in a multiple file input for $user.
 *
 * $options:
 *   type         Key of UPLOAD_TYPES the user chose ('any' accepts every file)
 *   title        Used when exactly one file is uploaded (otherwise each file keeps its own name)
 *   description, visibility ('private' | 'public'), category (materials category or '')
 *
 * Returns the number of files saved; problems are added to $errors.
 */
function handle_user_file_uploads(PDO $pdo, array $user, string $field, array $options, array &$errors): int
{
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return 0;
    }

    $type = isset(UPLOAD_TYPES[$options['type'] ?? 'any']) ? ($options['type'] ?? 'any') : 'any';
    $allowed_exts = UPLOAD_TYPES[$type][2];
    $chosen = count(array_filter($_FILES[$field]['error'], fn($e) => $e !== UPLOAD_ERR_NO_FILE));
    $title = $chosen === 1 && ($options['title'] ?? '') !== '' ? mb_substr($options['title'], 0, 200) : null;
    $description = (string) ($options['description'] ?? '');
    $visibility = ($options['visibility'] ?? 'private') === 'public' ? 'public' : 'private';
    $category = ($options['category'] ?? '') !== '' ? $options['category'] : null;

    $dir = user_files_dir();
    if (!ensure_private_dir($dir)) {
        $errors[] = 'The upload folder could not be created on the server.';
        return 0;
    }

    $f = $_FILES[$field];
    $max_bytes = user_max_file_bytes();
    $storage_left = USER_STORAGE_QUOTA - (int) $user['storage_used'];
    $saved = 0;
    $added_bytes = 0;

    $insert = $pdo->prepare(
        'INSERT INTO user_files (user_id, original_name, title, file_name, extension, mime_type, file_kind, file_size, width, height,
                                 description, visibility, category)
         VALUES (:uid, :original, :title, :stored, :ext, :mime, :kind, :size, :w, :h, :descr, :vis, :cat)'
    );

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

    foreach (array_keys($f['name']) as $i) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $original = basename(str_replace('\\', '/', (string) $f['name'][$i]));
        $original = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $original)), 0, 200);
        if ($original === '') {
            $original = 'file';
        }
        $label = '"' . $original . '"';

        if ($saved >= USER_MAX_FILES_PER_UPLOAD) {
            $errors[] = 'You can upload at most ' . USER_MAX_FILES_PER_UPLOAD . ' files at once. The rest were skipped.';
            break;
        }
        if (in_array($f['error'][$i], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'][$i] > $max_bytes) {
            $errors[] = $label . ' is too large. Maximum size is ' . format_bytes($max_bytes) . '.';
            continue;
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) {
            $errors[] = $label . ' could not be uploaded (error code ' . (int) $f['error'][$i] . ').';
            continue;
        }
        $size = (int) $f['size'][$i];
        if ($size === 0) {
            $errors[] = $label . ' is empty.';
            continue;
        }
        if ($added_bytes + $size > $storage_left) {
            $errors[] = $label . ' does not fit in your remaining storage ('
                . format_bytes(max(0, $storage_left - $added_bytes)) . ' left of ' . format_bytes(USER_STORAGE_QUOTA) . ').';
            continue;
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $ext = preg_match('/^[a-z0-9]{1,15}$/', $ext) ? $ext : '';
        if ($allowed_exts !== null && !in_array($ext, $allowed_exts, true)) {
            $errors[] = $label . ' is not a ' . UPLOAD_TYPES[$type][0] . ' file. Allowed: '
                . strtoupper(implode(', ', $allowed_exts)) . '. Choose "Any file" to upload other types.';
            continue;
        }
        $kind = file_kind_for($ext);
        $mime = $finfo ? (string) finfo_file($finfo, $f['tmp_name'][$i]) : 'application/octet-stream';

        // Images: read real pixel size; a file claiming to be an image that is not one is stored as "other"
        $width = $height = null;
        if ($kind === 'image' && isset(INLINE_MIME_TYPES[$ext])) {
            $info = @getimagesize($f['tmp_name'][$i]);
            if ($info === false) {
                $kind = 'other';
            } else {
                [$width, $height] = [(int) $info[0], (int) $info[1]];
            }
        }

        // Random name, no extension: never executable, never guessable
        $stored = 'uf_' . date('Ymd') . '_' . bin2hex(random_bytes(12));
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . $stored)) {
            $errors[] = $label . ' could not be saved on the server.';
            continue;
        }

        try {
            $insert->execute([
                ':uid' => (int) $user['id'],
                ':original' => $original,
                ':title' => $title,
                ':stored' => $stored,
                ':ext' => $ext,
                ':mime' => mb_substr($mime, 0, 150),
                ':kind' => $kind,
                ':size' => $size,
                ':w' => $width,
                ':h' => $height,
                ':descr' => $description !== '' ? mb_substr($description, 0, 300) : null,
                ':vis' => $visibility,
                ':cat' => $category,
            ]);
        } catch (PDOException $e) {
            @unlink($dir . $stored);
            $errors[] = $label . ' could not be recorded. Please try again.';
            continue;
        }

        $saved++;
        $added_bytes += $size;
    }

    if ($saved > 0) {
        $pdo->prepare('UPDATE users SET file_count = file_count + :n, storage_used = storage_used + :b WHERE id = :id')
            ->execute([':n' => $saved, ':b' => $added_bytes, ':id' => (int) $user['id']]);
    }

    return $saved;
}

/** Delete one user file (disk + row + the owner's counters). */
function delete_user_file(PDO $pdo, array $file): void
{
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM user_files WHERE id = :id');
        $del->execute([':id' => (int) $file['id']]);
        if ($del->rowCount() > 0) {
            $pdo->prepare(
                'UPDATE users SET file_count = GREATEST(file_count - 1, 0),
                                  storage_used = GREATEST(CAST(storage_used AS SIGNED) - :b, 0)
                 WHERE id = :uid'
            )->execute([':b' => (int) $file['file_size'], ':uid' => (int) $file['user_id']]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
    remove_user_file_from_disk($file['file_name']);
}

function remove_user_file_from_disk(?string $file_name): void
{
    $file_name = basename((string) $file_name);
    if ($file_name !== '' && strpos($file_name, 'uf_') === 0 && is_file(user_files_dir() . $file_name)) {
        @unlink(user_files_dir() . $file_name);
    }
}

/**
 * Delete a whole user account with all their files and avatar.
 * Files are removed from disk in batches, so this also works for users with thousands of files.
 */
function delete_user_account(PDO $pdo, int $user_id): void
{
    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = :id');
    $stmt->execute([':id' => $user_id]);
    $avatar = $stmt->fetchColumn();

    $last_id = 0;
    $batch = $pdo->prepare('SELECT id, file_name FROM user_files WHERE user_id = :uid AND id > :last ORDER BY id LIMIT 500');
    do {
        $batch->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $batch->bindValue(':last', $last_id, PDO::PARAM_INT);
        $batch->execute();
        $rows = $batch->fetchAll();
        foreach ($rows as $row) {
            remove_user_file_from_disk($row['file_name']);
            $last_id = (int) $row['id'];
        }
    } while (count($rows) === 500);

    // user_files and user_logins rows go with the user (ON DELETE CASCADE)
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $user_id]);
    delete_avatar_file($avatar ?: null);
}

/**
 * Save an uploaded avatar image (JPG, PNG, WEBP, GIF; max 2 MB).
 * Returns the new file name, null if no file was chosen; sets $error on problems.
 */
function handle_avatar_upload(string $field, ?string &$error): ?string
{
    $error = null;
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (is_array($file['error'])) {
        $error = 'Please choose a single image.';
        return null;
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > USER_MAX_AVATAR_BYTES) {
        $error = 'The profile photo is too large. Maximum size is 2 MB.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The profile photo could not be uploaded. Please try again.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if ($info === false || !isset($allowed[$info['mime']])) {
        $error = 'Profile photo must be a JPG, PNG, WEBP or GIF image.';
        return null;
    }
    $dir = avatars_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $error = 'The avatars folder could not be created.';
        return null;
    }
    $name = 'avatar_' . bin2hex(random_bytes(10)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        $error = 'The profile photo could not be saved on the server.';
        return null;
    }
    return $name;
}

function delete_avatar_file(?string $file_name): void
{
    $file_name = basename((string) $file_name);
    if ($file_name !== '' && strpos($file_name, 'avatar_') === 0 && is_file(avatars_dir() . $file_name)) {
        @unlink(avatars_dir() . $file_name);
    }
}

/* --------------------------------------------------------------
 * Shared UI helpers
 * ------------------------------------------------------------ */

/** Current URL's query string with some values replaced (for filters and pagination links). */
function query_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || (is_array($value))) {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
}

/** Numbered pagination (windowed, so 10,000 rows never print hundreds of links). */
function render_simple_pagination(int $page, int $total_pages): string
{
    if ($total_pages <= 1) {
        return '';
    }
    $link = function (int $p, string $label, string $aria = '') use ($page): string {
        $active = $p === $page && $aria === '';
        return '<a class="page-link' . ($active ? ' active' : '') . '" href="' . sanitize(query_with(['page' => $p])) . '"'
            . ($active ? ' aria-current="page"' : '') . ($aria !== '' ? ' aria-label="' . $aria . '"' : '') . '>' . $label . '</a>';
    };
    $gap = '<span class="page-link disabled">&hellip;</span>';

    $html = '<nav class="pagination-wrapper" aria-label="Pagination">';
    $html .= $page > 1 ? $link($page - 1, '<i class="fa-solid fa-chevron-left"></i>', 'Previous page')
        : '<span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>';
    foreach (page_window($page, $total_pages) as $p) {
        $html .= $p === 0 ? $gap : $link($p, (string) $p);
    }
    $html .= $page < $total_pages ? $link($page + 1, '<i class="fa-solid fa-chevron-right"></i>', 'Next page')
        : '<span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>';
    return $html . '</nav>';
}

/** Page numbers to show around $page: first, last, 2 either side; 0 marks a gap. */
function page_window(int $page, int $total_pages): array
{
    $pages = [];
    $prev = 0;
    for ($p = 1; $p <= $total_pages; $p++) {
        if ($p === 1 || $p === $total_pages || abs($p - $page) <= 2) {
            if ($prev && $p - $prev > 1) {
                $pages[] = 0;
            }
            $pages[] = $p;
            $prev = $p;
        } elseif ($p < $page - 2) {
            $p = $page - 3; // Jump ahead instead of looping over thousands of pages
        } elseif ($p > $page + 2) {
            $p = $total_pages - 1;
        }
    }
    return $pages;
}

/** Read ?page= as a positive integer. */
function current_page_number(): int
{
    return max(1, (int) input_text($_GET, 'page', '1'));
}

/* --------------------------------------------------------------
 * Public (shared) files: Materials page + shared.php
 * ------------------------------------------------------------ */

/**
 * One page of files that users chose to share publicly.
 * Files of blocked users are hidden. Filters: text search, materials category, upload type (pdf, word...).
 * Returns ['files', 'total', 'page', 'total_pages'].
 */
function get_shared_files_page(PDO $pdo, string $search, string $category, string $type, int $page, int $per_page = SHARED_FILES_PER_PAGE): array
{
    $where = ["f.visibility = 'public'", "u.status = 'active'"];
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(f.title LIKE :q1 OR f.original_name LIKE :q2 OR f.description LIKE :q3)';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    if ($category !== '') {
        $where[] = 'f.category = :category';
        $params[':category'] = $category;
    }
    $exts = UPLOAD_TYPES[$type][2] ?? null;
    if ($exts) {
        $placeholders = [];
        foreach (array_values($exts) as $i => $ext) {
            $placeholders[] = ':e' . $i;
            $params[':e' . $i] = $ext;
        }
        $where[] = 'f.extension IN (' . implode(',', $placeholders) . ')';
    }
    $where_sql = ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM user_files f JOIN users u ON u.id = f.user_id';

    $count = $pdo->prepare('SELECT COUNT(*)' . $from . $where_sql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min(max(1, $page), $total_pages);

    $stmt = $pdo->prepare(
        'SELECT f.id, f.user_id, f.original_name, f.title, f.extension, f.file_kind, f.file_size, f.width, f.height,
                f.description, f.category, f.download_count, f.created_at,
                u.full_name, u.username, u.avatar'
        . $from . $where_sql . ' ORDER BY f.created_at DESC, f.id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->execute();

    return ['files' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'total_pages' => $total_pages];
}

/** Card for one shared file (Materials page and shared.php). $prefix = '../' from sub-folders. */
function render_shared_card(array $file, string $prefix = ''): string
{
    $id = (int) $file['id'];
    $title = sanitize(user_file_title($file));
    $type = upload_type_for_extension((string) $file['extension']);
    $type_label = $file['extension'] !== '' ? strtoupper($file['extension']) : file_kind_label($file['file_kind']);

    $thumb = user_file_is_image($file)
        ? '<img src="' . $prefix . 'account/file.php?id=' . $id . '&amp;view=1" alt="' . $title . '" loading="lazy">'
        : '<span class="shared-thumb-icon type-' . $type . ' kind-' . sanitize($file['file_kind']) . '"><i class="fa-solid ' . user_file_icon($file) . '"></i></span>';

    return '
      <article class="shared-card">
        <a href="' . $prefix . 'shared_file.php?id=' . $id . '" class="shared-thumb" tabindex="-1" aria-hidden="true">' . $thumb . '
          <span class="shared-type">' . sanitize($type_label) . '</span>
        </a>
        <div class="shared-body">
          <h3 class="shared-title"><a href="' . $prefix . 'shared_file.php?id=' . $id . '">' . $title . '</a></h3>'
          . ($file['description'] ? '<p class="shared-desc">' . sanitize($file['description']) . '</p>' : '') . '
          <div class="shared-meta">
            <span class="shared-author">' . user_avatar_html($file, $prefix, 'avatar avatar-xs') . ' ' . sanitize($file['full_name']) . '</span>
            <span>' . format_bytes((int) $file['file_size']) . ' &middot; ' . date('M d, Y', strtotime($file['created_at'])) . '</span>
          </div>'
          . ($file['category'] ? '<span class="shared-category"><i class="fa-solid ' . category_icon($file['category']) . '"></i> ' . sanitize($file['category']) . '</span>' : '') . '
          <div class="shared-actions">
            <a href="' . $prefix . 'shared_file.php?id=' . $id . '" class="btn btn-primary btn-sm"><i class="fa-solid fa-eye"></i> View</a>
            <a href="' . $prefix . 'account/file.php?id=' . $id . '" class="btn btn-outline btn-sm" data-no-loader><i class="fa-solid fa-download"></i> Download</a>
          </div>
        </div>
      </article>';
}

/** Grid of shared file cards, or an empty message. */
function render_shared_grid(array $files, string $empty_message = 'No shared files match your search yet.'): string
{
    if (!$files) {
        return '<div class="empty-state empty-state-sm" style="grid-column: 1 / -1;">
          <div class="empty-icon"><i class="fa-solid fa-share-nodes"></i></div>
          <p class="empty-desc">' . sanitize($empty_message) . '</p>
          <a href="account/upload.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-cloud-arrow-up"></i> Share a file</a>
        </div>';
    }
    return implode('', array_map('render_shared_card', $files));
}
