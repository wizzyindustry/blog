<?php
/**
 * User File Delivery
 *   file.php?id=ID          Download (counts the download)
 *   file.php?id=ID&view=1   Show in the browser (safe types only: images, PDF, video, audio, plain text)
 *
 * Who can open a file:
 *   - public files (shared on the Materials page): everyone, unless the owner is blocked
 *   - private files: only the owner or a logged-in admin
 * Supports HTTP Range requests, so videos and audio can be seeked.
 */
require_once __DIR__ . '/../config/db.php';

start_session();
$viewer = current_user();
$is_admin = !empty($_SESSION['admin_logged_in']);

$stmt = $pdo->prepare(
    'SELECT f.*, u.status AS owner_status FROM user_files f JOIN users u ON u.id = f.user_id WHERE f.id = :id LIMIT 1'
);
$stmt->execute([':id' => (int) input_text($_GET, 'id', '0')]);
$file = $stmt->fetch();

$is_owner = $file && $viewer && (int) $file['user_id'] === (int) $viewer['id'];
$is_public = $file && $file['visibility'] === 'public' && $file['owner_status'] === 'active';

if ($file && !$is_owner && !$is_admin && !$is_public && !$viewer) {
    header('Location: login.php'); // Private file and nobody signed in
    exit;
}

$path = $file ? user_files_dir() . basename($file['file_name']) : '';
if (!$file || (!$is_owner && !$is_admin && !$is_public) || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found.';
    exit;
}

if ($viewer) {
    touch_user_activity();
}
session_write_close(); // Do not lock the session while a large file streams

$inline = isset($_GET['view']) && user_file_inline($file);
$mime = $inline ? INLINE_MIME_TYPES[$file['extension']] : 'application/octet-stream';
if ($inline && strpos($mime, 'text/') === 0) {
    $mime .= '; charset=utf-8';
}

$size = filesize($path);
$start = 0;
$end = $size - 1;

// Single byte-range support (video/audio seeking, resumable downloads)
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] === '' && $m[2] !== '') {
        $start = max(0, $size - (int) $m[2]);
    } else {
        $start = (int) $m[1];
        if ($m[2] !== '') {
            $end = min((int) $m[2], $size - 1);
        }
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
} elseif (!$inline && !$is_admin) {
    // Count full downloads (owner and other students; not admins)
    $pdo->prepare('UPDATE user_files SET download_count = download_count + 1 WHERE id = :id')
        ->execute([':id' => (int) $file['id']]);
}

$name = $file['original_name'];
$ascii = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '_') ?: 'download';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Accept-Ranges: bytes');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
if ($inline && strpos($mime, 'text/') === 0) {
    header('Content-Security-Policy: sandbox'); // Plain text is never run as a page
}

$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $end - $start + 1;
set_time_limit(0);
while ($left > 0 && !feof($fh) && !connection_aborted()) {
    $chunk = fread($fh, (int) min(1048576, $left));
    echo $chunk;
    flush();
    $left -= strlen($chunk);
}
fclose($fh);
exit;
