<?php
/**
 * File Delivery
 * UDOM Student Materials Blog
 *
 *   download.php?file=ID          Download an attached document (counts the download)
 *   download.php?file=ID&view=1   Show a PDF inside the browser (used by view.php)
 *   download.php?image=POST_ID    Download a post's image, only if the admin allowed it
 *
 * Files are looked up by database id (never by a path from the URL), so
 * visitors cannot request arbitrary files from the server.
 */

require_once __DIR__ . '/config/db.php';

/** Stop with a simple "not found" page. */
function file_not_found(string $message = 'This file is not available.'): void
{
  render_error_page(404, 'File not found', $message);
}

/** Send a file with safe headers. $inline = show in browser instead of saving. */
function send_file(string $path, string $mime, string $download_name, bool $inline): void
{
  // ASCII fallback name for old browsers + full UTF-8 name for modern ones
  $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $download_name);
  $ascii = trim($ascii, '_') !== '' ? $ascii : 'download';
  $disposition = $inline ? 'inline' : 'attachment';

  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($path));
  header('Content-Disposition: ' . $disposition . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: private, max-age=3600');
  readfile($path);
  exit;
}

/* ------------------------------------------------------------
 * 1. Post image download
 * ---------------------------------------------------------- */
if (isset($_GET['image'])) {
  $stmt = $pdo->prepare('SELECT slug, image, allow_image_download FROM posts WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => (int) $_GET['image']]);
  $post = $stmt->fetch();

  if (!$post || (int) $post['allow_image_download'] !== 1) {
    file_not_found('Downloading this image is not allowed.');
  }

  $file = basename((string) ($post['image'] ?: DEFAULT_POST_IMAGE));
  $path = __DIR__ . '/uploads/' . $file;
  if (!is_file($path)) {
    file_not_found();
  }

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $mime_by_ext = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
  if (!isset($mime_by_ext[$ext])) {
    file_not_found();
  }

  // Save as "<post-slug>.<ext>", e.g. "semester-registration-guide.jpg"
  send_file($path, $mime_by_ext[$ext], ($post['slug'] ?: 'udom-image') . '.' . $ext, false);
}

/* ------------------------------------------------------------
 * 2. Document download / PDF view
 * ---------------------------------------------------------- */
$stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = :id LIMIT 1');
$stmt->execute([':id' => (int) ($_GET['file'] ?? 0)]);
$doc = $stmt->fetch();

if (!$doc || !isset(DOCUMENT_TYPES[$doc['file_type']])) {
  file_not_found();
}

$path = documents_dir() . basename($doc['file_name']);
if (!is_file($path)) {
  file_not_found('The file was removed from the server.');
}

// Only PDFs are opened inside the browser; everything else is always downloaded
$inline = isset($_GET['view']) && $doc['file_type'] === 'pdf';

if (!$inline) {
  $count = $pdo->prepare('UPDATE attachments SET download_count = download_count + 1 WHERE id = :id');
  $count->execute([':id' => (int) $doc['id']]);
}

send_file($path, DOCUMENT_TYPES[$doc['file_type']][1], $doc['original_name'], $inline);
