<?php
/**
 * UDOM Student Materials - Single Post Detail & Comments
 */

require_once __DIR__ . '/config/db.php';

$post_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($post_id <= 0) {
  render_error_page(404, 'Post not found', 'This study material does not exist or the link is incomplete.');
}

// Fetch Post Details (prepared statement)
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $post_id]);
$post = $stmt->fetch();

if (!$post) {
  render_error_page(404, 'Post not found', 'This study material was removed or never existed. Try searching for it instead.');
}

$comment_error = '';
$comment_success = '';
$notices = [
  'commented' => 'Thank you. Your comment has been published.',
  'replied' => 'Your reply has been published.',
  'edited' => 'Your comment was updated.',
  'deleted' => 'The comment was deleted.',
];
foreach ($notices as $key => $text) {
  if (isset($_GET[$key])) {
    $comment_success = $text;
  }
}
$viewer = current_user(); // Signed-in student, or null for a guest
$author_name = '';
$comment_body = '';
$old = []; // Values to put back into a form after an error
$reply_to = (int) input_text($_GET, 'reply', '0');
$edit_id = (int) input_text($_GET, 'edit', '0');
$focus_id = 0; // Comment to scroll back to after an error

// ---------- Comment actions: add / reply, edit, delete (all POST + CSRF, checked on the server) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = input_text($_POST, 'action');
  $ip = client_ip();
  $comment_id = (int) input_text($_POST, 'comment_id', '0');

  if (!in_array($action, ['add_comment', 'edit_comment', 'delete_comment'], true)) {
    $comment_error = 'Unknown action.';
  } elseif (!csrf_valid()) {
    $comment_error = 'Your session expired. Please try again.';
  } elseif ($action === 'add_comment') {
    // New comment, or a reply when parent_id is set. Anyone may reply to anyone.
    $parent_id = (int) input_text($_POST, 'parent_id', '0');
    $parent = $parent_id > 0 ? find_comment($pdo, $parent_id, $post_id) : null;
    [$author_name, $comment_body, $problem] = validate_comment_input($viewer);

    if (input_text($_POST, 'website') !== '') {
      $comment_error = 'Your comment could not be posted.'; // Hidden field filled in: a spam bot
    } elseif (rate_limited('comment', $ip, 5, 10)) {
      $comment_error = 'You are posting comments too quickly. Please wait a few minutes and try again.';
    } elseif ($parent_id > 0 && (!$parent || $parent['deleted_at'] !== null)) {
      $comment_error = 'The comment you replied to no longer exists.';
    } elseif ($problem !== null) {
      $comment_error = $problem;
    } else {
      rate_hit('comment', $ip);
      try {
        $stmt = $pdo->prepare(
          'INSERT INTO comments (post_id, parent_id, user_id, name, comment) VALUES (:post_id, :parent, :uid, :name, :comment)'
        );
        $stmt->execute([
          ':post_id' => $post_id,
          ':parent' => $parent ? (int) $parent['id'] : null,
          ':uid' => $viewer ? (int) $viewer['id'] : null,
          ':name' => $author_name,
          ':comment' => $comment_body,
        ]);
        $new_id = (int) $pdo->lastInsertId();
        if (!$viewer) {
          remember_guest_comment($new_id); // Guests may edit/delete from this browser session
        }
        // Redirect after POST so refreshing the page does not submit the comment twice
        header('Location: post.php?id=' . $post_id . '&' . ($parent ? 'replied' : 'commented') . '=1#comment-' . $new_id);
        exit;
      } catch (PDOException $e) {
        error_log('Comment insert failed: ' . $e->getMessage());
        $comment_error = 'Failed to submit comment. Please try again.';
      }
    }
    if ($comment_error !== '' && $parent_id > 0) {
      $reply_to = $parent_id; // Re-open the reply form with what was typed
      $focus_id = $parent_id;
      $old = ['name' => $author_name, 'reply_body' => $comment_body];
      $comment_body = '';
    }
  } else {
    // Edit / delete: only the author (admin may delete for moderation, never edit)
    $target = $comment_id > 0 ? find_comment($pdo, $comment_id, $post_id) : null;

    if (!$target || $target['deleted_at'] !== null) {
      $comment_error = 'That comment no longer exists.';
    } elseif ($action === 'edit_comment') {
      if (!can_manage_comment($target)) {
        http_response_code(403);
        $comment_error = 'You can only edit your own comments.';
      } else {
        [, $new_body, $problem] = validate_comment_input($viewer, false);
        if ($problem !== null) {
          $comment_error = $problem;
          $edit_id = $focus_id = $comment_id;
          $old = ['edit_body' => $new_body];
        } else {
          if ($new_body !== $target['comment']) {
            $pdo->prepare('UPDATE comments SET comment = :c, updated_at = NOW() WHERE id = :id')
              ->execute([':c' => $new_body, ':id' => $comment_id]);
          }
          header('Location: post.php?id=' . $post_id . '&edited=1#comment-' . $comment_id);
          exit;
        }
      }
    } else { // delete_comment
      if (!can_manage_comment($target) && !is_admin_viewer()) {
        http_response_code(403);
        $comment_error = 'You can only delete your own comments.';
      } else {
        delete_comment($pdo, $target);
        header('Location: post.php?id=' . $post_id . '&deleted=1#comments');
        exit;
      }
    }
  }
}

// Highlight the matching category in the header menu
$active_nav = 'categories';
$active_category = (string) $post['category'];

$page_title = $post['title'];
$meta_description = $post['excerpt'] ?: meta_excerpt($post['content']);
$meta_canonical = 'post.php?id=' . $post_id;
$meta_image = 'uploads/' . basename((string) ($post['image'] ?: DEFAULT_POST_IMAGE));
$meta_type = 'article';

// Comments of this post as a tree (comments with their replies)
$comment_tree = get_comment_tree($pdo, $post_id);
$total_comments = $comment_tree['count'];
$comment_ctx = ['post_id' => $post_id, 'user' => $viewer, 'reply_to' => $reply_to, 'edit_id' => $edit_id, 'old' => $old];

// Documents attached to this post (PDF, Word, PowerPoint)
$attachments = get_post_attachments($pdo, $post_id);

// Prepare share URL and titles
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$share_title = urlencode($post['title'] . ' - UDOM Student Materials');
$share_url = urlencode($current_url);

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
  <div class="container post-detail-container">

    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb-nav" aria-label="Breadcrumbs">
      <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
      <span><i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i></span>
      <a href="index.php?category=<?php echo urlencode($post['category']); ?>">
        <?php echo sanitize($post['category']); ?>
      </a>
      <span><i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i></span>
      <span class="text-muted"><?php echo sanitize($post['title']); ?></span>
    </nav>

    <!-- Post Article Card -->
    <article class="post-detail-article">
      <header class="post-detail-header">
        <span class="post-detail-category"><?php echo sanitize($post['category']); ?></span>
        <h1 class="post-detail-title"><?php echo sanitize($post['title']); ?></h1>

        <div class="post-detail-meta">
          <span><i class="fa-solid fa-user"></i>
            <?php echo sanitize($post['author'] ?: 'UDOM Student Materials'); ?></span>
          <span><i class="fa-regular fa-calendar"></i>
            <?php echo date('F d, Y', strtotime($post['created_at'])); ?></span>
          <span><i class="fa-solid fa-comments"></i> <?php echo $total_comments; ?> Comments</span>
          <?php if ($attachments): ?>
            <a href="#attachments"><i class="fa-solid fa-paperclip"></i> <?php echo count($attachments); ?>
              document<?php echo count($attachments) === 1 ? '' : 's'; ?></a>
          <?php endif; ?>
        </div>
      </header>

      <!-- Featured Image -->
      <!-- Framed in the ratio chosen by the admin; the whole image is always visible -->
      <div class="post-detail-featured-image is-<?php echo post_image_orientation($post); ?>"
        style="<?php echo post_image_style($post); ?>">
        <img src="<?php echo post_image_url($post['image']); ?>" alt="<?php echo sanitize($post['title']); ?>"
          data-fallback="uploads/<?php echo DEFAULT_POST_IMAGE; ?>">
      </div>

      <?php if ((int) $post['allow_image_download'] === 1): ?>
        <!-- Shown only when the admin allowed downloading this image -->
        <div class="image-download-bar">
          <a href="download.php?image=<?php echo $post_id; ?>" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-download"></i> Download Image
          </a>
        </div>
      <?php endif; ?>

      <!-- Post Content: formatting tags allowed, scripts and event handlers removed -->
      <div class="post-detail-content">
        <?php echo clean_post_html($post['content']); ?>
      </div>

      <?php if ($attachments): ?>
        <!-- Attached documents: view in the browser first, or download -->
        <section class="attachments-section" id="attachments">
          <h2 class="attachments-title">
            <i class="fa-solid fa-paperclip"></i> Documents
            <span class="comments-count-badge"><?php echo count($attachments); ?></span>
          </h2>
          <ul class="attachments-list">
            <?php foreach ($attachments as $doc): ?>
              <li class="attachment-item">
                <span class="attachment-icon is-<?php echo sanitize($doc['file_type']); ?>">
                  <i class="fa-solid <?php echo document_icon($doc['file_type']); ?>"></i>
                </span>
                <div class="attachment-info">
                  <span class="attachment-name"><?php echo sanitize($doc['original_name']); ?></span>
                  <span class="attachment-meta">
                    <?php echo sanitize(document_label($doc['file_type'])); ?>
                    &bull; <?php echo format_bytes((int) $doc['file_size']); ?>
                    &bull; <?php echo (int) $doc['download_count']; ?>
                    download<?php echo (int) $doc['download_count'] === 1 ? '' : 's'; ?>
                  </span>
                </div>
                <div class="attachment-actions">
                  <a href="view.php?id=<?php echo (int) $doc['id']; ?>" class="btn btn-outline btn-sm"
                    title="<?php echo document_previewable($doc['file_type']) ? 'Read it in your browser' : 'Preview not available for this format'; ?>">
                    <i class="fa-solid fa-eye"></i> View
                  </a>
                  <a href="download.php?file=<?php echo (int) $doc['id']; ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-download"></i> Download
                  </a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <!-- Share Material Section -->
      <section class="post-share-section">
        <div class="share-title">
          <i class="fa-solid fa-share-nodes"></i> Share this educational material:
        </div>
        <div class="share-buttons">
          <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>"
            target="_blank" rel="noopener noreferrer" class="share-btn share-twitter" title="Share on X / Twitter"
            aria-label="Share on X">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank"
            rel="noopener noreferrer" class="share-btn share-facebook" title="Share on Facebook"
            aria-label="Share on Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
          <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share_url; ?>" target="_blank"
            rel="noopener noreferrer" class="share-btn share-linkedin" title="Share on LinkedIn"
            aria-label="Share on LinkedIn">
            <i class="fa-brands fa-linkedin-in"></i>
          </a>
          <a href="https://api.whatsapp.com/send?text=<?php echo $share_title . '%20' . $share_url; ?>" target="_blank"
            rel="noopener noreferrer" class="share-btn share-whatsapp" title="Share on WhatsApp"
            aria-label="Share on WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
          <button type="button" class="share-btn share-copy" id="copy-post-link" title="Copy Link to Clipboard"
            aria-label="Copy Link">
            <i class="fa-solid fa-link"></i>
          </button>
        </div>
      </section>
    </article>

    <!-- Comments Section -->
    <section class="comments-section" id="comments">
      <div class="comments-header">
        <h2><i class="fa-solid fa-comments"></i> Discussion &amp; Feedback</h2>
        <span class="comments-count-badge"><?php echo $total_comments; ?>
          <?php echo $total_comments === 1 ? 'Comment' : 'Comments'; ?></span>
      </div>

      <!-- Feedback / Alert Notifications -->
      <?php if (!empty($comment_success)): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i> <?php echo sanitize($comment_success); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($comment_error)): ?>
        <div class="alert alert-danger">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($comment_error); ?>
        </div>
      <?php endif; ?>

      <!-- Add Comment Form -->
      <div class="comment-form-card">
        <h3 class="comment-form-title">
          <i class="fa-solid fa-pen"></i> Leave a Comment
        </h3>
        <form action="post.php?id=<?php echo $post_id; ?>#comments" method="POST" id="comment-form">
          <input type="hidden" name="action" value="add_comment">
          <?php echo comment_form_fields($viewer, 'comment', $author_name, $comment_body, 'Write your constructive thoughts or academic question here...'); ?>
          <div class="comment-form-footer">
            <button type="submit" class="btn btn-primary" data-loading-text="Posting...">
              <i class="fa-solid fa-paper-plane"></i> Submit Comment
            </button>
            <?php if (!$viewer): ?>
              <span class="text-muted comment-form-note">
                <a href="account/login.php">Sign in</a> to comment with your account and edit or delete your comments any time.
              </span>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Comments List -->
      <div class="comments-list"<?php echo $focus_id ? ' data-focus="comment-' . $focus_id . '"' : ''; ?>>
        <?php if ($comment_tree['roots']): ?>
          <?php foreach ($comment_tree['roots'] as $root): ?>
            <?php echo render_comment($root, $comment_tree, 1, $comment_ctx); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-comment-dots"></i></div>
            <h3 class="empty-title">No Comments Yet</h3>
            <p class="empty-desc">Be the first to join the academic discussion on this material.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Reply form copied under a comment by main.js when "Reply" is clicked -->
      <template id="reply-template">
        <form action="post.php?id=<?php echo $post_id; ?>" method="POST" class="comment-reply-form">
          <input type="hidden" name="action" value="add_comment">
          <input type="hidden" name="parent_id" value="">
          <?php echo comment_form_fields($viewer, 'reply-new', '', '', 'Write your reply...'); ?>
          <div class="comment-form-actions">
            <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Posting..."><i class="fa-solid fa-paper-plane"></i> Post Reply</button>
            <button type="button" class="btn btn-outline btn-sm" data-reply-cancel>Cancel</button>
          </div>
        </form>
      </template>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>