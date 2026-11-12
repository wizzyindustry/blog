<?php
/**
 * Document Viewer
 * UDOM Student Materials Blog
 *
 * view.php?id=ATTACHMENT_ID
 * Lets visitors read a document before deciding to download it.
 *   PDF  -> shown in the browser's built-in PDF viewer
 *   DOCX -> text, headings, lists, tables and pictures
 *   PPTX -> each slide's title, text and pictures
 *   DOC / PPT (old formats) -> cannot be previewed, download only
 */

require_once __DIR__ . '/config/db.php';

$stmt = $pdo->prepare(
  'SELECT a.*, p.title AS post_title, p.category AS post_category
   FROM attachments a JOIN posts p ON p.id = a.post_id
   WHERE a.id = :id LIMIT 1'
);
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$doc = $stmt->fetch();

if (!$doc) {
  render_error_page(404, 'Document not found', 'This document was removed or the link is incomplete.');
}

$type = $doc['file_type'];
$path = documents_dir() . basename($doc['file_name']);
$file_exists = is_file($path);

// Build the preview for Word / PowerPoint (PDF uses the browser viewer)
$preview_html = null;
if ($file_exists && $type === 'docx') {
  $preview_html = render_docx_preview($path);
} elseif ($file_exists && $type === 'pptx') {
  $preview_html = render_pptx_preview($path);
}

$download_url = 'download.php?file=' . (int) $doc['id'];
$active_nav = 'categories';
$active_category = (string) $doc['post_category'];
$page_title = $doc['original_name'];
$meta_description = 'Preview and download "' . $doc['original_name'] . '", a document attached to a UDOM Student Materials post.';

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
  <div class="container doc-viewer-container">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="Breadcrumbs">
      <a href="index.php"><i class="fa-solid fa-book-open"></i> Materials</a>
      <span><i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i></span>
      <a href="post.php?id=<?php echo (int) $doc['post_id']; ?>"><?php echo sanitize($doc['post_title']); ?></a>
      <span><i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i></span>
      <span class="text-muted"><?php echo sanitize($doc['original_name']); ?></span>
    </nav>

    <!-- Document header: name, type, size, actions -->
    <div class="doc-viewer-header">
      <div class="doc-viewer-title">
        <span class="attachment-icon is-<?php echo sanitize($type); ?>">
          <i class="fa-solid <?php echo document_icon($type); ?>"></i>
        </span>
        <div>
          <h1><?php echo sanitize($doc['original_name']); ?></h1>
          <p class="text-muted">
            <?php echo sanitize(document_label($type)); ?> &bull; <?php echo format_bytes((int) $doc['file_size']); ?>
            &bull; <?php echo (int) $doc['download_count']; ?> download<?php echo (int) $doc['download_count'] === 1 ? '' : 's'; ?>
          </p>
        </div>
      </div>
      <div class="doc-viewer-actions">
        <a href="post.php?id=<?php echo (int) $doc['post_id']; ?>#attachments" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to post
        </a>
        <?php if ($file_exists): ?>
          <a href="<?php echo $download_url; ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-download"></i> Download
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Preview -->
    <?php if (!$file_exists): ?>
      <div class="doc-viewer-message">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>This file is no longer available</h2>
        <p class="text-muted">It may have been removed. Please check back later.</p>
      </div>

    <?php elseif ($type === 'pdf'): ?>
      <div class="doc-viewer-frame-wrap">
        <iframe class="doc-viewer-frame" src="<?php echo $download_url; ?>&amp;view=1"
          title="Preview of <?php echo sanitize($doc['original_name']); ?>"></iframe>
      </div>
      <p class="doc-viewer-note text-muted">
        <i class="fa-solid fa-circle-info"></i>
        If the document does not appear (some phones cannot show PDFs inside a page),
        <a href="<?php echo $download_url; ?>&amp;view=1" target="_blank" rel="noopener">open it in a new tab</a>
        or download it.
      </p>

    <?php elseif ($preview_html !== null && trim($preview_html) !== ''): ?>
      <div class="doc-viewer-note text-muted">
        <i class="fa-solid fa-circle-info"></i>
        This is a simplified preview of the <?php echo $type === 'pptx' ? 'slides' : 'document'; ?>.
        Download the file to see the original layout, fonts and animations.
      </div>
      <article class="doc-preview <?php echo $type === 'pptx' ? 'doc-preview-slides' : 'doc-preview-page'; ?>">
        <?php echo $preview_html; // Built from escaped text and validated images only ?>
      </article>

    <?php else: ?>
      <div class="doc-viewer-message">
        <i class="fa-solid <?php echo document_icon($type); ?>"></i>
        <h2>Preview not available</h2>
        <p class="text-muted">
          <?php if (!document_previewable($type)): ?>
            Older <?php echo strtoupper(sanitize($type)); ?> files cannot be shown in the browser.
            Download the file and open it with Microsoft Office, LibreOffice or Google Docs.
          <?php else: ?>
            This file could not be previewed. Download it to open it on your device.
          <?php endif; ?>
        </p>
        <a href="<?php echo $download_url; ?>" class="btn btn-primary">
          <i class="fa-solid fa-download"></i> Download (<?php echo format_bytes((int) $doc['file_size']); ?>)
        </a>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
