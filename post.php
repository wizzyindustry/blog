<?php
/**
 * UDOM Student Materials - Single Post Detail & Comments
 */

require_once __DIR__ . '/config/db.php';

$post_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($post_id <= 0) {
  header('Location: index.php');
  exit;
}

// Fetch Post Details (prepared statement)
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $post_id]);
$post = $stmt->fetch();

if (!$post) {
  header('Location: index.php');
  exit;
}

$comment_error = '';
$comment_success = isset($_GET['commented']) ? 'Thank you. Your comment has been published.' : '';
$author_name = '';
$comment_body = '';

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
  $author_name = trim((string) ($_POST['name'] ?? ''));
  $comment_body = trim((string) ($_POST['comment'] ?? ''));

  if ($author_name === '' || $comment_body === '') {
    $comment_error = 'Please enter both your name and your comment.';
  } elseif (mb_strlen($author_name) < 2 || mb_strlen($author_name) > 100) {
    $comment_error = 'Name must be between 2 and 100 characters.';
  } elseif (mb_strlen($comment_body) < 3 || mb_strlen($comment_body) > 2000) {
    $comment_error = 'Comment must be between 3 and 2000 characters.';
  } else {
    try {
      $stmt = $pdo->prepare("INSERT INTO comments (post_id, name, comment) VALUES (:post_id, :name, :comment)");
      $stmt->execute([
        ':post_id' => $post_id,
        ':name' => $author_name,
        ':comment' => $comment_body
      ]);
      // Redirect after POST so refreshing the page does not submit the comment twice
      header('Location: post.php?id=' . $post_id . '&commented=1#comments');
      exit;
    } catch (PDOException $e) {
      $comment_error = 'Failed to submit comment. Please try again.';
    }
  }
}

// Highlight the matching category in the header menu
$active_nav = 'categories';
$active_category = (string) $post['category'];

$page_title = $post['title'];

// Fetch Comments for this Post
$comment_stmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = :id ORDER BY created_at DESC");
$comment_stmt->execute([':id' => $post_id]);
$comments = $comment_stmt->fetchAll();
$total_comments = count($comments);

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
        </div>
      </header>

      <!-- Featured Image -->
      <!-- Framed in the ratio chosen by the admin; the whole image is always visible -->
      <div class="post-detail-featured-image is-<?php echo post_image_orientation($post); ?>"
        style="<?php echo post_image_style($post); ?>">
        <img src="<?php echo post_image_url($post['image']); ?>" alt="<?php echo sanitize($post['title']); ?>"
          onerror="this.onerror=null;this.src='uploads/<?php echo DEFAULT_POST_IMAGE; ?>';">
      </div>

      <!-- Post Content: formatting tags allowed, scripts and event handlers removed -->
      <div class="post-detail-content">
        <?php echo clean_post_html($post['content']); ?>
      </div>

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
        <form action="post.php?id=<?php echo $post_id; ?>#comments" method="POST">
          <input type="hidden" name="action" value="add_comment">

          <div class="form-group">
            <label for="comment-name" class="form-label">Your Full Name</label>
            <input type="text" name="name" id="comment-name" class="form-control" placeholder="e.g. Kelvin Massawe"
              required minlength="2" maxlength="100" value="<?php echo sanitize($author_name); ?>">
          </div>

          <div class="form-group">
            <label for="comment-text" class="form-label">Your Comment or Question</label>
            <textarea name="comment" id="comment-text" class="form-control"
              placeholder="Write your constructive thoughts or academic question here..." required
              minlength="3" maxlength="2000"><?php echo sanitize($comment_body); ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-paper-plane"></i> Submit Comment
          </button>
        </form>
      </div>

      <!-- Comments List -->
      <div class="comments-list">
        <?php if (!empty($comments)): ?>
          <?php foreach ($comments as $comment): ?>
            <div class="comment-item">
              <div class="comment-avatar">
                <i class="fa-solid fa-user"></i>
              </div>
              <div class="comment-content">
                <div class="comment-meta">
                  <span class="comment-author"><?php echo sanitize($comment['name']); ?></span>
                  <span class="comment-date">
                    <i class="fa-regular fa-clock"></i>
                    <?php echo date('M d, Y \a\t H:i', strtotime($comment['created_at'])); ?>
                  </span>
                </div>
                <p class="comment-text">
                  <?php echo nl2br(sanitize($comment['comment'])); ?>
                </p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-comment-dots"></i></div>
            <h3 class="empty-title">No Comments Yet</h3>
            <p class="empty-desc">Be the first to join the academic discussion on this material.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>