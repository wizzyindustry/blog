<?php
/**
 * Edit a file's details and who can see it.
 */
require_once __DIR__ . '/auth_check.php';

$stmt = $pdo->prepare('SELECT * FROM user_files WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => (int) input_text($_GET, 'id', input_text($_POST, 'id', '0')), ':uid' => (int) $user['id']]);
$file = $stmt->fetch();
if (!$file) {
    flash('error', 'That file was not found.');
    header('Location: files.php');
    exit;
}

$page_title = 'Edit File';
$errors = [];
$details = [
    'title' => user_file_title($file),
    'description' => (string) $file['description'],
    'visibility' => $file['visibility'],
    'category' => (string) $file['category'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        [$details, $errors] = validate_file_details();
        if (!$errors) {
            $pdo->prepare(
                'UPDATE user_files SET title = :t, description = :d, visibility = :v, category = :c WHERE id = :id AND user_id = :uid'
            )->execute([
                ':t' => $details['title'] !== '' ? $details['title'] : null,
                ':d' => $details['description'] !== '' ? $details['description'] : null,
                ':v' => $details['visibility'],
                ':c' => $details['category'] !== '' ? $details['category'] : null,
                ':id' => (int) $file['id'],
                ':uid' => (int) $user['id'],
            ]);
            flash('success', '"' . ($details['title'] ?: $file['original_name']) . '" was saved'
                . ($details['visibility'] === 'public' ? ' and is shared on the Materials page.' : ' as private.'));
            header('Location: files.php');
            exit;
        }
    }
}

$fid = (int) $file['id'];
require __DIR__ . '/includes/account_header.php';
?>

<main class="app-content">
  <div class="page-heading">
    <div>
      <a href="files.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> My Files</a>
      <h1><i class="fa-solid fa-pen-to-square"></i> Edit File</h1>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i>
      <div><?php foreach ($errors as $e): ?><div><?php echo sanitize($e); ?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <div class="profile-grid">
    <aside class="admin-card file-preview-card">
      <?php if (user_file_is_image($file)): ?>
        <img src="file.php?id=<?php echo $fid; ?>&amp;view=1" alt="" class="file-preview-img">
      <?php else: ?>
        <span class="shared-thumb-icon kind-<?php echo sanitize($file['file_kind']); ?>"><i class="fa-solid <?php echo user_file_icon($file); ?>"></i></span>
      <?php endif; ?>
      <p class="file-name-cell"><strong><?php echo sanitize($file['original_name']); ?></strong></p>
      <p class="text-muted"><?php echo format_bytes((int) $file['file_size']); ?> &middot; <?php echo number_format((int) $file['download_count']); ?> downloads</p>
      <div class="table-actions">
        <?php if (user_file_inline($file)): ?>
          <a href="file.php?id=<?php echo $fid; ?>&amp;view=1" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i> Open</a>
        <?php endif; ?>
        <a href="file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm" data-no-loader><i class="fa-solid fa-download"></i> Download</a>
        <?php if ($file['visibility'] === 'public'): ?>
          <a href="../shared_file.php?id=<?php echo $fid; ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-earth-africa"></i> Public page</a>
        <?php endif; ?>
      </div>
    </aside>

    <section class="admin-card">
      <form action="edit_file.php?id=<?php echo $fid; ?>" method="POST">
        <?php echo csrf_field(); ?>
        <fieldset class="choice-fieldset">
          <legend class="step-title">Who can see it?</legend>
          <div class="visibility-grid">
            <label class="visibility-option">
              <input type="radio" name="visibility" value="private" <?php echo $details['visibility'] !== 'public' ? 'checked' : ''; ?>>
              <span class="visibility-box"><i class="fa-solid fa-lock"></i><strong>Private</strong><span>Only you can see and download it.</span></span>
            </label>
            <label class="visibility-option">
              <input type="radio" name="visibility" value="public" <?php echo $details['visibility'] === 'public' ? 'checked' : ''; ?>>
              <span class="visibility-box"><i class="fa-solid fa-earth-africa"></i><strong>Public</strong><span>Shown on the Materials page for every student.</span></span>
            </label>
          </div>
        </fieldset>

        <div class="form-group">
          <label for="title" class="form-label"><i class="fa-solid fa-heading"></i> Title</label>
          <input type="text" name="title" id="title" class="form-control" maxlength="200" value="<?php echo sanitize($details['title']); ?>">
        </div>

        <div class="form-group" id="category-group">
          <label for="category" class="form-label"><i class="fa-solid fa-folder-tree"></i> Category</label>
          <select name="category" id="category" class="form-control">
            <option value="">No category</option>
            <?php foreach (get_categories() as $cat): ?>
              <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $details['category'] === $cat['name'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="description" class="form-label"><i class="fa-solid fa-align-left"></i> Description</label>
          <textarea name="description" id="description" class="form-control" rows="3" maxlength="300"><?php echo sanitize($details['description']); ?></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
          <a href="files.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </section>
  </div>
</main>

<?php require __DIR__ . '/includes/account_footer.php'; ?>
