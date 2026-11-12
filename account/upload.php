<?php
/**
 * Upload Files
 * The user chooses WHAT they are uploading (PDF, Word, PowerPoint, Images... or any file)
 * and WHO can see it (only me, or everyone on the Materials page).
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'Upload Files';
$errors = [];
$max_bytes = user_max_file_bytes();

// Pre-select a type from the link, e.g. upload.php?type=pdf
$type = input_text($_POST, 'type', input_text($_GET, 'type', 'pdf'));
$type = isset(UPLOAD_TYPES[$type]) && $type !== 'any' ? $type : 'pdf'; // An upload type must always be chosen
$details = ['title' => '', 'description' => '', 'visibility' => 'private', 'category' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_too_large()) {
        $errors[] = 'The selected files are larger than the server allows in one upload (' . format_bytes(ini_bytes('post_max_size')) . '). Upload fewer files at a time.';
    } elseif (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (rate_limited('upload', 'u:' . (int) $user['id'], 30, 60)) {
        $errors[] = 'You have uploaded a lot in the last hour. Please wait a while before uploading more.';
    } else {
        rate_hit('upload', 'u:' . (int) $user['id']);
        [$details, $errors] = validate_file_details();
        $saved = 0;
        if (!$errors) {
            $saved = handle_user_file_uploads($pdo, $user, 'files', $details + ['type' => $type], $errors);
        }

        if ($saved === 0 && !$errors) {
            $errors[] = 'Please choose at least one file to upload.';
        }
        if ($saved > 0) {
            $message = $saved . ' file' . ($saved === 1 ? '' : 's') . ' uploaded'
                . ($details['visibility'] === 'public' ? ' and shared on the Materials page.' : ' (private: only you can see ' . ($saved === 1 ? 'it' : 'them') . ').');
            if ($errors) {
                $message .= ' Some files were skipped: ' . implode(' ', $errors);
            }
            flash('success', $message);
            header('Location: files.php');
            exit;
        }
    }
}

$storage_left = max(0, USER_STORAGE_QUOTA - (int) $user['storage_used']);

require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-cloud-arrow-up"></i> Upload Files</h1>
      <p class="text-muted">Choose what you are uploading and who can see it.</p>
    </div>
    <a href="files.php" class="btn btn-outline"><i class="fa-solid fa-folder-open"></i> My Files</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div><?php foreach ($errors as $e): ?><div><?php echo sanitize($e); ?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <form action="upload.php" method="POST" enctype="multipart/form-data" id="upload-form" class="upload-layout"
    data-max-file="<?php echo $max_bytes; ?>" data-max-count="<?php echo USER_MAX_FILES_PER_UPLOAD; ?>"
    data-storage-left="<?php echo $storage_left; ?>" data-max-post="<?php echo ini_bytes('post_max_size'); ?>">
    <?php echo csrf_field(); ?>

    <section class="admin-card">
      <!-- Step 1: what kind of file -->
      <fieldset class="choice-fieldset">
        <legend class="step-title"><span class="step-num">1</span> What are you uploading?</legend>
        <div class="type-grid">
          <?php foreach (array_diff_key(UPLOAD_TYPES, ['any' => 1]) as $key => [$label, $icon, $exts]): ?>
            <label class="type-option">
              <input type="radio" name="type" value="<?php echo $key; ?>" <?php echo $type === $key ? 'checked' : ''; ?>
                data-accept="<?php echo sanitize(upload_type_accept($key)); ?>"
                data-label="<?php echo sanitize($label); ?>"
                data-exts="<?php echo sanitize($exts ? implode(',', $exts) : ''); ?>">
              <span class="type-option-box type-<?php echo $key; ?>">
                <i class="fa-solid <?php echo $icon; ?>"></i>
                <span><?php echo $label; ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="form-hint" id="type-hint">
          Allowed: <?php echo sanitize(strtoupper(implode(', ', UPLOAD_TYPES[$type][2]))); ?>
        </p>
      </fieldset>

      <!-- Step 2: choose files -->
      <div class="step-title"><span class="step-num">2</span> Choose files</div>
      <label class="dropzone" id="dropzone" for="files">
        <input type="file" name="files[]" id="files" multiple class="dropzone-input"
          accept="<?php echo sanitize(upload_type_accept($type)); ?>">
        <span class="dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
        <strong>Drag and drop files here</strong>
        <span class="text-muted">or click to browse your device</span>
        <span class="dropzone-limits">
          Up to <?php echo USER_MAX_FILES_PER_UPLOAD; ?> files at once &middot; max <?php echo format_bytes($max_bytes); ?> each
          &middot; <?php echo format_bytes($storage_left); ?> storage left
        </span>
      </label>

      <ul class="upload-queue" id="upload-queue" aria-live="polite"></ul>
      <div class="upload-error" id="upload-error" role="alert" hidden></div>
    </section>

    <section class="admin-card">
      <!-- Step 3: who can see it -->
      <fieldset class="choice-fieldset">
        <legend class="step-title"><span class="step-num">3</span> Who can see it?</legend>
        <div class="visibility-grid">
          <label class="visibility-option">
            <input type="radio" name="visibility" value="private" <?php echo $details['visibility'] !== 'public' ? 'checked' : ''; ?>>
            <span class="visibility-box">
              <i class="fa-solid fa-lock"></i>
              <strong>Private</strong>
              <span>Only you can see and download it.</span>
            </span>
          </label>
          <label class="visibility-option">
            <input type="radio" name="visibility" value="public" <?php echo $details['visibility'] === 'public' ? 'checked' : ''; ?>>
            <span class="visibility-box">
              <i class="fa-solid fa-earth-africa"></i>
              <strong>Public</strong>
              <span>Shared on the Materials page so every student can view and download it.</span>
            </span>
          </label>
        </div>
      </fieldset>

      <!-- Step 4: details -->
      <div class="step-title"><span class="step-num">4</span> Details <span class="text-muted step-optional">(optional)</span></div>

      <div class="form-group">
        <label for="title" class="form-label"><i class="fa-solid fa-heading"></i> Title</label>
        <input type="text" name="title" id="title" class="form-control" maxlength="200"
          placeholder="e.g. Data Structures - Week 4 Lecture Notes" value="<?php echo sanitize($details['title']); ?>">
        <p class="form-hint">Used when you upload one file. With several files, each keeps its own file name.</p>
      </div>

      <div class="form-group" id="category-group">
        <label for="category" class="form-label"><i class="fa-solid fa-folder-tree"></i> Category</label>
        <select name="category" id="category" class="form-control">
          <option value="">No category</option>
          <?php foreach (get_categories() as $cat): ?>
            <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $details['category'] === $cat['name'] ? 'selected' : ''; ?>>
              <?php echo sanitize($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="form-hint">Helps other students find your file when they filter the Materials page.</p>
      </div>

      <div class="form-group">
        <label for="description" class="form-label"><i class="fa-solid fa-align-left"></i> Description</label>
        <textarea name="description" id="description" class="form-control" rows="3" maxlength="300"
          placeholder="What is this file about? e.g. Summary of chapters 1-3 with worked examples"><?php echo sanitize($details['description']); ?></textarea>
      </div>

      <div class="upload-progress" id="upload-progress" hidden>
        <div class="progress"><div class="progress-bar" id="upload-progress-bar" style="width: 0%;"></div></div>
        <span id="upload-progress-text">Uploading... 0%</span>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="upload-submit" data-loading-text="Uploading...">
          <i class="fa-solid fa-upload"></i> Upload
        </button>
        <a href="files.php" class="btn btn-outline">Cancel</a>
      </div>
    </section>
  </form>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
