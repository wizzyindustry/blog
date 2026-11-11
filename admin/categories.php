<?php
/**
 * Manage Categories (Add / Edit / Delete)
 * UDOM Student Materials Blog
 *
 * URLs:
 *   categories.php             List all categories + "Add Category" form
 *   categories.php?edit=ID     Same page with the form switched to edit mode
 *   categories.php?delete=ID   Delete confirmation (asks where to move posts)
 *
 * All changes are POSTed back to this page with a CSRF token and saved with
 * prepared statements. Changes appear everywhere at once because every page
 * (navigation, filters, landing page, footer, post form) reads the
 * categories table:
 *   - Rename: posts.category is linked to categories.name with
 *             ON UPDATE CASCADE, so MySQL renames it on every post too.
 *   - Delete: posts are first moved to another category (in a transaction);
 *             ON DELETE RESTRICT stops a category with posts from being removed.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Categories';

$error = '';
$field_errors = [];

/** Load one category by id (with post count), or null. */
function load_category(int $id): ?array
{
  foreach (get_categories() as $cat) {
    if ((int) $cat['id'] === $id) {
      return $cat;
    }
  }
  return null;
}

/* ------------------------------------------------------------
 * Handle form submissions
 * ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = input_text($_POST, 'action');
  $id = (int) ($_POST['id'] ?? 0);

  if (!csrf_valid()) {
    $error = 'Your session expired. Please try again.';
  } elseif ($action === 'create') {
    // ---------- Add a new category ----------
    [$data, $field_errors] = validate_category_input($pdo);
    if (!$field_errors) {
      try {
        $stmt = $pdo->prepare('INSERT INTO categories (name, description, icon) VALUES (:name, :description, :icon)');
        $stmt->execute([
          ':name' => $data['name'],
          ':description' => $data['description'] !== '' ? $data['description'] : null,
          ':icon' => $data['icon'],
        ]);
        flash('success', 'Category "' . $data['name'] . '" was added. It now appears in the menu, filters and post form.');
        header('Location: categories.php');
        exit;
      } catch (PDOException $e) {
        $error = 'The category could not be saved. Please try again.';
      }
    }
  } elseif ($action === 'update') {
    // ---------- Rename / edit a category ----------
    $existing = load_category($id);
    if (!$existing) {
      flash('error', 'That category no longer exists.');
      header('Location: categories.php');
      exit;
    }

    [$data, $field_errors] = validate_category_input($pdo, $id);
    if (!$field_errors) {
      try {
        // Renaming here also renames posts.category (ON UPDATE CASCADE)
        $stmt = $pdo->prepare('UPDATE categories SET name = :name, description = :description, icon = :icon WHERE id = :id');
        $stmt->execute([
          ':name' => $data['name'],
          ':description' => $data['description'] !== '' ? $data['description'] : null,
          ':icon' => $data['icon'],
          ':id' => $id,
        ]);

        $msg = 'Category "' . $data['name'] . '" was updated.';
        if ($existing['name'] !== $data['name'] && (int) $existing['post_count'] > 0) {
          $msg .= ' Its ' . (int) $existing['post_count'] . ' post(s) were moved to the new name automatically.';
        }
        flash('success', $msg);
        header('Location: categories.php');
        exit;
      } catch (PDOException $e) {
        $error = 'The category could not be updated. Please try again.';
      }
    }
  } elseif ($action === 'delete') {
    // ---------- Delete a category (moving its posts first) ----------
    $existing = load_category($id);
    if (!$existing) {
      flash('error', 'That category was already deleted.');
      header('Location: categories.php');
      exit;
    }

    $post_count = (int) $existing['post_count'];
    $move_to = $post_count > 0 ? load_category((int) ($_POST['move_to'] ?? 0)) : null;

    if ($post_count > 0 && (!$move_to || (int) $move_to['id'] === $id)) {
      flash('error', 'Choose another category to move the ' . $post_count . ' post(s) to before deleting "' . $existing['name'] . '".');
      header('Location: categories.php?delete=' . $id);
      exit;
    }

    try {
      $pdo->beginTransaction();

      if ($move_to) {
        $move = $pdo->prepare('UPDATE posts SET category = :to WHERE category = :from');
        $move->execute([':to' => $move_to['name'], ':from' => $existing['name']]);
      }

      $del = $pdo->prepare('DELETE FROM categories WHERE id = :id');
      $del->execute([':id' => $id]);

      $pdo->commit();

      $msg = 'Category "' . $existing['name'] . '" was deleted.';
      if ($move_to) {
        $msg .= ' ' . $post_count . ' post(s) were moved to "' . $move_to['name'] . '".';
      }
      flash('success', $msg);
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash('error', 'The category could not be deleted. No changes were made.');
    }
    header('Location: categories.php');
    exit;
  } else {
    $error = 'Unknown action.';
  }
}

/* ------------------------------------------------------------
 * Decide what the page shows
 * ---------------------------------------------------------- */
$categories = get_categories(true);

$edit_cat = isset($_GET['edit']) ? load_category((int) $_GET['edit']) : null;
$delete_cat = isset($_GET['delete']) ? load_category((int) $_GET['delete']) : null;

// Form values: re-show what was typed after a validation error,
// otherwise the category being edited, otherwise empty.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data)) {
  $form = $data;
  if (($action ?? '') === 'update') {
    $edit_cat = load_category($id);
  }
} elseif ($edit_cat) {
  $form = ['name' => $edit_cat['name'], 'description' => (string) $edit_cat['description'], 'icon' => $edit_cat['icon']];
} else {
  $form = ['name' => '', 'description' => '', 'icon' => 'fa-folder'];
}
if ($field_errors && !$error) {
  $error = 'Please fix the highlighted fields.';
}

$flash_success = get_flash('success');
$flash_error = get_flash('error');

$field_error = function (string $field) use ($field_errors): string {
  return isset($field_errors[$field])
    ? '<div class="field-error"><i class="fa-solid fa-circle-exclamation"></i> ' . sanitize($field_errors[$field]) . '</div>'
    : '';
};

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container">

    <div class="card-header-flex">
      <div>
        <h1><i class="fa-solid fa-folder-tree"></i> Categories</h1>
        <p class="text-muted">
          Add, rename or remove categories. Changes show up instantly in the site menu, filters,
          landing page, footer and the post form.
        </p>
      </div>
    </div>

    <?php if ($flash_success): ?>
      <div class="alert alert-success" role="status">
        <i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?>
      </div>
    <?php endif; ?>
    <?php if ($flash_error || $error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($flash_error ?: $error); ?>
      </div>
    <?php endif; ?>

    <div class="categories-layout">

      <!-- Left: Add / Edit form, or Delete confirmation -->
      <div class="admin-card">
        <?php if ($delete_cat): ?>
          <?php $count = (int) $delete_cat['post_count']; ?>
          <h2 class="admin-card-title text-danger"><i class="fa-solid fa-trash-can"></i> Delete Category</h2>
          <p>
            You are about to delete <strong><?php echo sanitize($delete_cat['name']); ?></strong>.
          </p>

          <form action="categories.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int) $delete_cat['id']; ?>">

            <?php if ($count > 0): ?>
              <?php $others = array_filter($categories, fn($c) => (int) $c['id'] !== (int) $delete_cat['id']); ?>
              <?php if ($others): ?>
                <div class="form-group">
                  <label for="move_to" class="form-label">
                    This category has <?php echo $count; ?> post<?php echo $count === 1 ? '' : 's'; ?>. Move them to:
                    <span class="required-mark">*</span>
                  </label>
                  <select name="move_to" id="move_to" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($others as $other): ?>
                      <option value="<?php echo (int) $other['id']; ?>"><?php echo sanitize($other['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php else: ?>
                <div class="alert alert-danger">
                  <i class="fa-solid fa-circle-info"></i>
                  This is the only category and it has posts. Add another category first, then delete this one.
                </div>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-muted">This category has no posts, so nothing else will change.</p>
            <?php endif; ?>

            <div class="form-actions">
              <?php if ($count === 0 || !empty($others)): ?>
                <button type="submit" class="btn btn-danger" data-loading-text="Deleting...">
                  <i class="fa-solid fa-trash-can"></i> Delete Category
                </button>
              <?php endif; ?>
              <a href="categories.php" class="btn btn-outline">Cancel</a>
            </div>
          </form>

        <?php else: ?>
          <h2 class="admin-card-title">
            <?php if ($edit_cat): ?>
              <i class="fa-solid fa-pen-to-square"></i> Edit Category
            <?php else: ?>
              <i class="fa-solid fa-folder-plus"></i> Add Category
            <?php endif; ?>
          </h2>

          <form action="categories.php<?php echo $edit_cat ? '?edit=' . (int) $edit_cat['id'] : ''; ?>" method="POST" id="category-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $edit_cat ? 'update' : 'create'; ?>">
            <?php if ($edit_cat): ?>
              <input type="hidden" name="id" value="<?php echo (int) $edit_cat['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
              <label for="cat-name" class="form-label">Name <span class="required-mark">*</span></label>
              <input type="text" name="name" id="cat-name"
                class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>"
                placeholder="e.g. Computer Science" value="<?php echo sanitize($form['name']); ?>"
                required minlength="2" maxlength="50">
              <?php if ($edit_cat && (int) $edit_cat['post_count'] > 0): ?>
                <small class="text-muted">
                  Renaming also updates its <?php echo (int) $edit_cat['post_count']; ?> post(s).
                </small>
              <?php endif; ?>
              <?php echo $field_error('name'); ?>
            </div>

            <div class="form-group">
              <label for="cat-description" class="form-label">Description</label>
              <textarea name="description" id="cat-description" rows="3"
                class="form-control <?php echo isset($field_errors['description']) ? 'is-invalid' : ''; ?>"
                maxlength="300" data-counter="cat-desc-counter"
                placeholder="One or two sentences shown on the landing page."><?php echo sanitize($form['description']); ?></textarea>
              <small class="text-muted"><span id="cat-desc-counter">0</span> / 300 characters</small>
              <?php echo $field_error('description'); ?>
            </div>

            <fieldset class="form-group icon-picker-group">
              <legend class="form-label">Icon</legend>
              <div class="icon-picker">
                <?php foreach (CATEGORY_ICONS as $icon_class => $icon_label): ?>
                  <label class="icon-option" title="<?php echo sanitize($icon_label); ?>">
                    <input type="radio" name="icon" value="<?php echo sanitize($icon_class); ?>"
                      <?php echo $form['icon'] === $icon_class ? 'checked' : ''; ?>>
                    <span class="icon-tile"><i class="fa-solid <?php echo sanitize($icon_class); ?>"></i></span>
                    <span class="visually-hidden"><?php echo sanitize($icon_label); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php echo $field_error('icon'); ?>
            </fieldset>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" data-loading-text="Saving...">
                <?php if ($edit_cat): ?>
                  <i class="fa-solid fa-floppy-disk"></i> Save Changes
                <?php else: ?>
                  <i class="fa-solid fa-plus"></i> Add Category
                <?php endif; ?>
              </button>
              <?php if ($edit_cat): ?>
                <a href="categories.php" class="btn btn-outline">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Right: list of categories -->
      <div class="admin-card">
        <div class="card-header-flex">
          <h2 class="admin-card-title"><i class="fa-solid fa-list"></i> All Categories (<?php echo count($categories); ?>)</h2>
        </div>

        <?php if ($categories): ?>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Posts</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categories as $cat): ?>
                  <tr class="<?php echo ($edit_cat && (int) $edit_cat['id'] === (int) $cat['id']) || ($delete_cat && (int) $delete_cat['id'] === (int) $cat['id']) ? 'row-selected' : ''; ?>">
                    <td>
                      <div class="category-cell">
                        <span class="category-cell-icon"><i class="fa-solid <?php echo category_icon($cat['name']); ?>"></i></span>
                        <div>
                          <div class="table-title"><?php echo sanitize($cat['name']); ?></div>
                          <?php if ($cat['description']): ?>
                            <div class="text-muted category-cell-desc"><?php echo sanitize($cat['description']); ?></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td>
                      <a href="../<?php echo sanitize(home_url('', $cat['name'])); ?>" target="_blank" title="View on site">
                        <?php echo (int) $cat['post_count']; ?>
                      </a>
                    </td>
                    <td style="text-align: right;">
                      <div class="table-actions" style="justify-content: flex-end;">
                        <a href="categories.php?edit=<?php echo (int) $cat['id']; ?>" class="btn btn-outline btn-sm"
                          title="Edit" aria-label="Edit <?php echo sanitize($cat['name']); ?>">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="categories.php?delete=<?php echo (int) $cat['id']; ?>" class="btn btn-danger btn-sm"
                          title="Delete" aria-label="Delete <?php echo sanitize($cat['name']); ?>">
                          <i class="fa-solid fa-trash-can"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h3 class="empty-title">No categories yet</h3>
            <p class="empty-desc">Add your first category with the form. You need at least one before creating posts.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
