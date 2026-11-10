<?php
/**
 * Edit Existing Post
 * UDOM Student Materials Blog
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Edit Post';

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Load the post being edited
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $post_id]);
$post = $stmt->fetch();

if (!$post) {
  flash('error', 'That post does not exist or was already deleted.');
  header('Location: dashboard.php');
  exit;
}

// Pre-fill the form with the saved values
$title = $post['title'];
$category = normalize_category($post['category']);
$author = $post['author'];
$excerpt = $post['excerpt'];
$content = $post['content'];
$current_image = $post['image'] ?: DEFAULT_POST_IMAGE;
$image_ratio = $post['image_ratio'] ?: 'auto';
$current_image_size = [$post['image_width'], $post['image_height']];
$error = '';
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid()) {
    $error = 'Your session expired. Please submit the form again.';
  } else {
    // 1. Validate text fields
    [$data, $field_errors] = validate_post_input();
    ['title' => $title, 'category' => $category, 'author' => $author,
      'excerpt' => $excerpt, 'content' => $content, 'image_ratio' => $image_ratio] = $data;

    // 2. Optional replacement image
    $new_image = null;
    if (!$field_errors) {
      $new_image = handle_image_upload('image', $upload_error, $new_image_size);
      if ($upload_error) {
        $field_errors['image'] = $upload_error;
      }
    }

    // 3. Update the post (prepared statement)
    if (!$field_errors) {
      try {
        // Only regenerate the slug when the title changed
        $slug = $title !== $post['title'] ? unique_slug($pdo, $title, $post_id) : $post['slug'];

        $update = $pdo->prepare('
          UPDATE posts
          SET title = :title, slug = :slug, category = :category, excerpt = :excerpt,
              content = :content, image = :image, image_ratio = :image_ratio,
              image_width = :image_width, image_height = :image_height, author = :author
          WHERE id = :id
        ');
        $update->execute([
          ':title' => $title,
          ':slug' => $slug,
          ':category' => $category,
          ':excerpt' => $excerpt,
          ':content' => $content,
          ':image' => $new_image ?? $current_image,
          ':image_ratio' => $image_ratio,
          // New image: store its real size; otherwise keep the saved size
          ':image_width' => $new_image ? $new_image_size[0] : $current_image_size[0],
          ':image_height' => $new_image ? $new_image_size[1] : $current_image_size[1],
          ':author' => $author,
          ':id' => $post_id,
        ]);

        // Remove the old uploaded image once the new one is saved
        if ($new_image) {
          delete_uploaded_image($current_image);
        }

        flash('success', 'Post "' . $title . '" was updated successfully.');
        header('Location: dashboard.php');
        exit;
      } catch (PDOException $e) {
        delete_uploaded_image($new_image);
        $error = 'The post could not be updated. Please try again.';
      }
    } else {
      $error = 'Please fix the highlighted fields below.';
    }
  }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container container-narrow">

    <div class="card-header-flex">
      <div>
        <h1><i class="fa-solid fa-pen-to-square"></i> Edit Post</h1>
        <p class="text-muted">Post #<?php echo $post_id; ?> &bull; Last updated
          <?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?></p>
      </div>
      <div class="table-actions">
        <a href="../post.php?id=<?php echo $post_id; ?>" target="_blank" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-eye"></i> View
        </a>
        <a href="dashboard.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back
        </a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <?php
      $form_action = 'edit.php?id=' . $post_id;
      $submit_label = 'Save Changes';
      $submit_icon = 'fa-floppy-disk';
      require __DIR__ . '/includes/post_form.php';
      ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
