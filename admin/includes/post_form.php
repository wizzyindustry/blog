<?php
/**
 * Shared Post Form (used by create.php and edit.php)
 *
 * Expects these variables from the including page:
 *   $form_action   - URL the form posts to
 *   $submit_label  - Text on the submit button
 *   $submit_icon   - Font Awesome icon for the submit button
 *   $title, $category, $author, $excerpt, $content - current field values
 *   $current_image - (edit only) current image file name, or null
 *   $current_image_size - (edit only) [width, height] of the current image
 *   $image_ratio   - display ratio key from IMAGE_RATIOS ('auto', '16:9', ...)
 *   $field_errors  - array of field => message for inline errors
 */
$current_image = $current_image ?? null;
$current_image_size = $current_image_size ?? [null, null];  // [width, height] of saved image
$image_ratio = $image_ratio ?? 'auto';                      // Saved / submitted display ratio
$allow_image_download = !empty($allow_image_download);       // Show "Download Image" button
$existing_attachments = $existing_attachments ?? [];        // (edit only) documents already attached
$remove_attachment_ids = $remove_attachment_ids ?? [];      // Documents ticked for removal
$field_errors = $field_errors ?? [];

/** Print the inline error for one field, if any. */
$field_error = function (string $field) use ($field_errors): string {
  return isset($field_errors[$field])
    ? '<div class="field-error"><i class="fa-solid fa-circle-exclamation"></i> ' . sanitize($field_errors[$field]) . '</div>'
    : '';
};
?>
<form action="<?php echo sanitize($form_action); ?>" method="POST" enctype="multipart/form-data" id="post-form" data-upload-form
  novalidate>
  <?php echo csrf_field(); ?>

  <!-- Title -->
  <div class="form-group">
    <label for="title" class="form-label">Post Title <span class="required-mark">*</span></label>
    <input type="text" name="title" id="title" class="form-control <?php echo isset($field_errors['title']) ? 'is-invalid' : ''; ?>"
      placeholder="e.g. UDOM Semester Registration Guidelines" value="<?php echo sanitize($title); ?>"
      required minlength="5" maxlength="255">
    <?php echo $field_error('title'); ?>
  </div>

  <div class="form-row">
    <!-- Category -->
    <div class="form-group">
      <label for="category" class="form-label">Category <span class="required-mark">*</span></label>
      <select name="category" id="category" class="form-control <?php echo isset($field_errors['category']) ? 'is-invalid' : ''; ?>" required>
        <option value="">Select a category</option>
        <?php foreach (category_names() as $cat): ?>
          <option value="<?php echo sanitize($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
            <?php echo sanitize($cat); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">
        Need a new one? <a href="categories.php" target="_blank"><i class="fa-solid fa-plus"></i> Manage categories</a>
      </small>
      <?php echo $field_error('category'); ?>
    </div>

    <!-- Author -->
    <div class="form-group">
      <label for="author" class="form-label">Author Name</label>
      <input type="text" name="author" id="author" class="form-control <?php echo isset($field_errors['author']) ? 'is-invalid' : ''; ?>"
        placeholder="UDOM Student Materials" value="<?php echo sanitize($author); ?>" maxlength="100">
      <?php echo $field_error('author'); ?>
    </div>
  </div>

  <!-- Image Upload -->
  <div class="form-group">
    <label for="image-upload" class="form-label">
      <?php echo $current_image ? 'Replace Featured Image' : 'Featured Image'; ?>
    </label>
    <input type="file" name="image" id="image-upload" class="form-control <?php echo isset($field_errors['image']) ? 'is-invalid' : ''; ?>"
      accept="image/jpeg,image/png,image/webp,image/gif">
    <small class="text-muted">
      JPG, PNG, WEBP or GIF, up to 5 MB.
      <?php echo $current_image ? 'Leave empty to keep the current image.' : 'If left empty, a default image is used.'; ?>
    </small>
    <?php echo $field_error('image'); ?>

    <?php
    // Starting shape of the preview: the saved ratio + saved image size (edit),
    // or "Original" for a new post. JavaScript updates it live.
    $preview_post = [
      'image_ratio' => $image_ratio,
      'image_width' => $current_image_size[0] ?? null,
      'image_height' => $current_image_size[1] ?? null,
    ];
    ?>
    <div class="image-info" id="image-info" aria-live="polite">
      <?php if (!empty($current_image_size[0]) && !empty($current_image_size[1])): ?>
        <i class="fa-solid fa-circle-info"></i>
        Current image: <?php echo (int) $current_image_size[0]; ?> &times; <?php echo (int) $current_image_size[1]; ?> px
        (<?php echo post_image_orientation(['image_width' => $current_image_size[0], 'image_height' => $current_image_size[1]]); ?>)
      <?php endif; ?>
    </div>

    <div class="image-preview-box" id="image-preview-box" style="<?php echo post_image_style($preview_post); ?>"
      data-width="<?php echo (int) ($current_image_size[0] ?? 0); ?>"
      data-height="<?php echo (int) ($current_image_size[1] ?? 0); ?>">
      <?php if ($current_image): ?>
        <img id="preview-img" src="<?php echo post_image_url($current_image, '../'); ?>" alt="Current featured image"
          data-fallback="../uploads/<?php echo DEFAULT_POST_IMAGE; ?>">
      <?php else: ?>
        <span id="preview-placeholder" class="text-muted"><i class="fa-regular fa-image"></i> Image preview</span>
        <img id="preview-img" src="" alt="Selected image preview" style="display: none;">
      <?php endif; ?>
    </div>
    <small class="text-muted">Preview: this is how the image frame will look on the website.</small>
  </div>

  <!-- Image display ratio -->
  <fieldset class="form-group ratio-picker-group">
    <legend class="form-label">Image Display Ratio</legend>
    <p class="text-muted ratio-help">
      Choose the frame shape for this image. The whole image is always shown and never cropped.
      <strong>Original</strong> uses the image's own shape and suits most images.
    </p>
    <div class="ratio-picker" id="ratio-picker">
      <?php foreach (IMAGE_RATIOS as $ratio_key => [$ratio_label, $ratio_hint, $rw, $rh]): ?>
        <label class="ratio-option">
          <input type="radio" name="image_ratio" value="<?php echo sanitize($ratio_key); ?>"
            data-w="<?php echo $rw; ?>" data-h="<?php echo $rh; ?>"
            <?php echo $image_ratio === $ratio_key ? 'checked' : ''; ?>>
          <span class="ratio-tile">
            <span class="ratio-shape-wrap">
              <?php if ($ratio_key === 'auto'): ?>
                <i class="fa-solid fa-expand ratio-auto-icon"></i>
              <?php else: ?>
                <span class="ratio-shape" style="--ratio: <?php echo round($rw / $rh, 4); ?>;"></span>
              <?php endif; ?>
            </span>
            <span class="ratio-label"><?php echo sanitize($ratio_label); ?></span>
            <span class="ratio-hint"><?php echo sanitize($ratio_hint); ?></span>
            <span class="ratio-badge" hidden>Best fit</span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php echo $field_error('image_ratio'); ?>
  </fieldset>

  <!-- Allow visitors to download the image -->
  <div class="form-group">
    <label class="check-option">
      <input type="checkbox" name="allow_image_download" value="1" <?php echo $allow_image_download ? 'checked' : ''; ?>>
      <span>
        <strong>Allow visitors to download this image</strong>
        <span class="text-muted">Shows a "Download Image" button under the image on the post page.</span>
      </span>
    </label>
  </div>

  <!-- Documents (PDF, Word, PowerPoint) -->
  <fieldset class="form-group documents-group">
    <legend class="form-label"><i class="fa-solid fa-paperclip"></i> Documents for students</legend>
    <p class="text-muted ratio-help">
      Attach PDF, Word (DOC, DOCX) or PowerPoint (PPT, PPTX) files. Visitors can view them in the browser
      or download them. Up to <?php echo MAX_ATTACHMENTS_PER_POST; ?> files per post,
      <?php echo format_bytes(MAX_DOCUMENT_BYTES); ?> each, and <?php echo format_bytes(ini_bytes('post_max_size')); ?>
      in total per save.
    </p>

    <?php if ($existing_attachments): ?>
      <ul class="attachments-list attachments-list-admin">
        <?php foreach ($existing_attachments as $doc): ?>
          <li class="attachment-item">
            <span class="attachment-icon is-<?php echo sanitize($doc['file_type']); ?>">
              <i class="fa-solid <?php echo document_icon($doc['file_type']); ?>"></i>
            </span>
            <div class="attachment-info">
              <a class="attachment-name" href="../view.php?id=<?php echo (int) $doc['id']; ?>" target="_blank">
                <?php echo sanitize($doc['original_name']); ?>
              </a>
              <span class="attachment-meta">
                <?php echo sanitize(document_label($doc['file_type'])); ?> &bull;
                <?php echo format_bytes((int) $doc['file_size']); ?> &bull;
                <?php echo (int) $doc['download_count']; ?> downloads
              </span>
            </div>
            <label class="remove-toggle">
              <input type="checkbox" name="remove_attachments[]" value="<?php echo (int) $doc['id']; ?>"
                <?php echo in_array((int) $doc['id'], $remove_attachment_ids, true) ? 'checked' : ''; ?>>
              <i class="fa-solid fa-trash-can"></i> Remove
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <label for="documents-upload" class="file-drop" id="documents-drop">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <span><strong>Choose documents</strong> or drag them here</span>
      <span class="text-muted">PDF, DOC, DOCX, PPT, PPTX</span>
    </label>
    <input type="file" name="documents[]" id="documents-upload" class="visually-hidden" multiple
      accept=".pdf,.doc,.docx,.ppt,.pptx,application/pdf,application/msword,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation"
      data-max-files="<?php echo MAX_ATTACHMENTS_PER_POST - count($existing_attachments); ?>"
      data-max-bytes="<?php echo MAX_DOCUMENT_BYTES; ?>">
    <ul class="selected-files" id="selected-files" aria-live="polite"></ul>
    <?php echo $field_error('documents'); ?>
  </fieldset>

  <!-- Excerpt -->
  <div class="form-group">
    <label for="excerpt" class="form-label">Excerpt (short summary) <span class="required-mark">*</span></label>
    <textarea name="excerpt" id="excerpt" class="form-control <?php echo isset($field_errors['excerpt']) ? 'is-invalid' : ''; ?>"
      rows="3" placeholder="A 1-3 sentence summary shown on the post card..." required minlength="10"
      maxlength="500" data-counter="excerpt-counter"><?php echo sanitize($excerpt); ?></textarea>
    <small class="text-muted"><span id="excerpt-counter">0</span> / 500 characters</small>
    <?php echo $field_error('excerpt'); ?>
  </div>

  <!-- Rich Content with formatting toolbar -->
  <div class="form-group">
    <label for="content" class="form-label">Content <span class="required-mark">*</span></label>
    <div class="editor-toolbar" role="toolbar" aria-label="Formatting">
      <button type="button" class="editor-btn" data-wrap="strong" title="Bold"><i class="fa-solid fa-bold"></i></button>
      <button type="button" class="editor-btn" data-wrap="em" title="Italic"><i class="fa-solid fa-italic"></i></button>
      <button type="button" class="editor-btn" data-wrap="h3" title="Heading"><i class="fa-solid fa-heading"></i></button>
      <button type="button" class="editor-btn" data-wrap="p" title="Paragraph"><i class="fa-solid fa-paragraph"></i></button>
      <button type="button" class="editor-btn" data-list="ul" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
      <button type="button" class="editor-btn" data-list="ol" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
      <button type="button" class="editor-btn" data-wrap="blockquote" title="Quote"><i class="fa-solid fa-quote-left"></i></button>
      <button type="button" class="editor-btn" data-link="1" title="Link"><i class="fa-solid fa-link"></i></button>
    </div>
    <textarea name="content" id="content" class="form-control editor-textarea <?php echo isset($field_errors['content']) ? 'is-invalid' : ''; ?>"
      placeholder="Write the full post here. Select text and use the toolbar to format it, or write plain paragraphs separated by a blank line."
      required minlength="20"><?php echo htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <small class="text-muted">Supported formatting: paragraphs, headings, bold, italic, lists, quotes and links.</small>
    <?php echo $field_error('content'); ?>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary" data-loading-text="Saving...">
      <i class="fa-solid <?php echo $submit_icon; ?>"></i> <?php echo sanitize($submit_label); ?>
    </button>
    <a href="posts.php" class="btn btn-outline">Cancel</a>
  </div>
</form>
