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
  header('Location: posts.php');
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
$allow_image_download = (int) $post['allow_image_download'];
$existing_attachments = get_post_attachments($pdo, $post_id);
$remove_attachment_ids = [];
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

    // Documents ticked "Remove" (only ids that really belong to this post)
    $existing_ids = array_map('intval', array_column($existing_attachments, 'id'));
    $remove_attachment_ids = array_values(array_intersect(
      $existing_ids,
      array_map('intval', (array) ($_POST['remove_attachments'] ?? []))
    ));
    $slots = MAX_ATTACHMENTS_PER_POST - (count($existing_ids) - count($remove_attachment_ids));

    // 2. Optional replacement image + new documents
    $new_image = null;
    $new_documents = [];
    if (!$field_errors) {
      $new_image = handle_image_upload('image', $upload_error, $new_image_size);
      if ($upload_error) {
        $field_errors['image'] = $upload_error;
      }

      $doc_errors = [];
      $new_documents = handle_document_uploads('documents', max(0, $slots), $doc_errors);
      if ($doc_errors) {
        $field_errors['documents'] = implode(' ', $doc_errors);
      }

      // Any problem: remove the files saved in this attempt
      if ($field_errors) {
        delete_uploaded_image($new_image);
        foreach ($new_documents as $doc) {
          delete_document_file($doc['file_name']);
        }
        $new_image = null;
        $new_documents = [];
      }
    }

    // 3. Update the post, add/remove documents together (transaction)
    if (!$field_errors) {
      try {
        // Only regenerate the slug when the title changed
        $slug = $title !== $post['title'] ? unique_slug($pdo, $title, $post_id) : $post['slug'];

        $pdo->beginTransaction();
        $update = $pdo->prepare('
          UPDATE posts
          SET title = :title, slug = :slug, category = :category, excerpt = :excerpt,
              content = :content, image = :image, image_ratio = :image_ratio,
              image_width = :image_width, image_height = :image_height,
              allow_image_download = :allow_image_download, author = :author
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
          ':allow_image_download' => $allow_image_download,
          ':author' => $author,
          ':id' => $post_id,
        ]);

        // Add the new documents
        save_attachments($pdo, $post_id, $new_documents);

        // Remove the documents ticked "Remove"
        $removed_files = [];
        if ($remove_attachment_ids) {
          $del = $pdo->prepare('DELETE FROM attachments WHERE id = :id AND post_id = :post_id');
          foreach ($existing_attachments as $doc) {
            if (in_array((int) $doc['id'], $remove_attachment_ids, true)) {
              $del->execute([':id' => (int) $doc['id'], ':post_id' => $post_id]);
              $removed_files[] = $doc['file_name'];
            }
          }
        }
        $pdo->commit();

        // Files are deleted from disk only after the database change succeeded
        foreach ($removed_files as $file) {
          delete_document_file($file);
        }
        if ($new_image) {
          delete_uploaded_image($current_image);
        }

        $notes = [];
        if ($new_documents) {
          $notes[] = count($new_documents) . ' document(s) added';
        }
        if ($removed_files) {
          $notes[] = count($removed_files) . ' removed';
        }
        flash('success', 'Post "' . $title . '" was updated successfully' . ($notes ? ' (' . implode(', ', $notes) . ')' : '') . '.');
        header('Location: posts.php');
        exit;
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        delete_uploaded_image($new_image);
        foreach ($new_documents as $doc) {
          delete_document_file($doc['file_name']);
        }
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
        <a href="posts.php" class="btn btn-outline btn-sm">
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
