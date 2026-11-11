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
$allow_image_download = 0;
$error = '';
$field_errors = [];

if (post_too_large()) {
  // PHP discarded the whole form because the files were bigger than post_max_size
  $error = 'The files are too large to upload together (limit ' . format_bytes(ini_bytes('post_max_size'))
    . ' per save). Upload fewer or smaller documents.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid()) {
    $error = 'Your session expired. Please submit the form again.';
  } else {
    // 1. Validate text fields
    [$data, $field_errors] = validate_post_input();
    ['title' => $title, 'category' => $category, 'author' => $author,
      'excerpt' => $excerpt, 'content' => $content, 'image_ratio' => $image_ratio,
      'allow_image_download' => $allow_image_download] = $data;

    // 2. Validate and store the image and documents only if the text fields are valid
    $image_name = null;
    $new_documents = [];
    if (!$field_errors) {
      $image_name = handle_image_upload('image', $upload_error, $image_size);
      if ($upload_error) {
        $field_errors['image'] = $upload_error;
      }

      $doc_errors = [];
      $new_documents = handle_document_uploads('documents', MAX_ATTACHMENTS_PER_POST, $doc_errors);
      if ($doc_errors) {
        $field_errors['documents'] = implode(' ', $doc_errors);
      }

      // Any problem: remove the files saved in this attempt
      if ($field_errors) {
        delete_uploaded_image($image_name);
        foreach ($new_documents as $doc) {
          delete_document_file($doc['file_name']);
        }
        $image_name = null;
        $new_documents = [];
      }
    }

    // 3. Insert the post and its documents together (transaction)
    if (!$field_errors) {
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('
          INSERT INTO posts (title, slug, category, excerpt, content, image, image_ratio, image_width, image_height,
                             allow_image_download, author)
          VALUES (:title, :slug, :category, :excerpt, :content, :image, :image_ratio, :image_width, :image_height,
                  :allow_image_download, :author)
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
          ':allow_image_download' => $allow_image_download,
          ':author' => $author,
        ]);
        save_attachments($pdo, (int) $pdo->lastInsertId(), $new_documents);
        $pdo->commit();

        $docs_msg = $new_documents ? ' with ' . count($new_documents) . ' document(s)' : '';
        flash('success', 'Post "' . $title . '" was published successfully' . $docs_msg . '.');
        header('Location: posts.php');
        exit;
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        // Don't leave orphaned files behind
        delete_uploaded_image($image_name);
        foreach ($new_documents as $doc) {
          delete_document_file($doc['file_name']);
        }
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
      <a href="posts.php" class="btn btn-outline btn-sm">
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
