<?php
/**
 * Create New Blog Post
 * UDOM Student Materials Blog
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Create New Post';

// Default form values
$title = '';
$category = '';
$author = 'UDOM Student Materials';
$excerpt = '';
$content = '';
$image_ratio = 'auto'; // How the image is framed on the site
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

    // 2. Validate and store the image only if the text fields are valid
    $image_name = null;
    if (!$field_errors) {
      $image_name = handle_image_upload('image', $upload_error, $image_size);
      if ($upload_error) {
        $field_errors['image'] = $upload_error;
      }
    }

    // 3. Insert the post (prepared statement)
    if (!$field_errors) {
      try {
        $stmt = $pdo->prepare('
          INSERT INTO posts (title, slug, category, excerpt, content, image, image_ratio, image_width, image_height, author)
          VALUES (:title, :slug, :category, :excerpt, :content, :image, :image_ratio, :image_width, :image_height, :author)
        ');
        $stmt->execute([
          ':title' => $title,
          ':slug' => unique_slug($pdo, $title),
          ':category' => $category,
          ':excerpt' => $excerpt,
          ':content' => $content,
          ':image' => $image_name ?? DEFAULT_POST_IMAGE,
          ':image_ratio' => $image_ratio,
          // Real size of the uploaded image (the default image is 800 x 450)
          ':image_width' => $image_name ? $image_size[0] : 800,
          ':image_height' => $image_name ? $image_size[1] : 450,
          ':author' => $author,
        ]);

        flash('success', 'Post "' . $title . '" was published successfully.');
        header('Location: dashboard.php');
        exit;
      } catch (PDOException $e) {
        delete_uploaded_image($image_name); // Don't leave an orphaned file behind
        $error = 'The post could not be saved. Please try again.';
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
        <h1><i class="fa-solid fa-file-circle-plus"></i> Create New Post</h1>
        <p class="text-muted">Publish a new article, guide or announcement.</p>
      </div>
      <a href="dashboard.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <?php
      $form_action = 'create.php';
      $submit_label = 'Publish Post';
      $submit_icon = 'fa-cloud-arrow-up';
      $current_image = null;
      require __DIR__ . '/includes/post_form.php';
      ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
