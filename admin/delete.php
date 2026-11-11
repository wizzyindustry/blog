<?php
/**
 * Delete Post Handler
 * UDOM Student Materials Blog
 *
 * URL: admin/delete.php?id=123
 * Only accepts POST requests with a valid CSRF token (sent by the
 * Delete button on the dashboard), so a malicious link cannot delete posts.
 * Comments are removed automatically by the ON DELETE CASCADE foreign key.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('error', 'Delete request rejected. Please use the Delete button on the dashboard.');
    header('Location: posts.php');
    exit;
}

try {
    // Look up the post first so its image file can be removed too
    $stmt = $pdo->prepare('SELECT title, image FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $post_id]);
    $post = $stmt->fetch();

    if ($post) {
        // Remember the attached documents (their rows are removed by ON DELETE CASCADE)
        $attachments = get_post_attachments($pdo, $post_id);

        $del = $pdo->prepare('DELETE FROM posts WHERE id = :id');
        $del->execute([':id' => $post_id]);

        // Clean up the files on disk
        delete_uploaded_image($post['image']);
        foreach ($attachments as $doc) {
            delete_document_file($doc['file_name']);
        }

        $extra = $attachments ? ', comments and ' . count($attachments) . ' document(s)' : ' and its comments';
        flash('success', 'Post "' . $post['title'] . '"' . $extra . ' were deleted.');
    } else {
        flash('error', 'Post not found or already deleted.');
    }
} catch (PDOException $e) {
    flash('error', 'The post could not be deleted. Please try again.');
}

header('Location: posts.php');
exit;
