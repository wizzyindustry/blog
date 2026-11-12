<?php
/**
 * Comments, Replies & Ownership
 * UDOM Student Materials Blog
 *
 * - Anyone can comment on a post and reply to any comment (replies can be nested).
 * - Only the AUTHOR of a comment can edit or delete it:
 *     signed-in student -> owns comments posted with their account (forever, on any device)
 *     guest             -> owns comments posted from this browser session only
 *   The admin can delete any comment (moderation) but cannot edit someone else's words.
 * - Deleting a comment that has replies keeps a "This comment was deleted" placeholder,
 *   so other people's replies are never removed by someone else's action.
 */

const COMMENT_MAX_LENGTH = 2000;
const COMMENT_MAX_DEPTH = 3; // Deeper replies are shown at this indent with "Replying to <name>"

/** Read and validate the comment text (and guest name) from $_POST. Returns [name, body, error|null]. */
function validate_comment_input(?array $user, bool $need_name = true): array
{
    $name = $user ? $user['full_name'] : trim(preg_replace('/\s+/u', ' ', input_text($_POST, 'name')));
    $body = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', input_text($_POST, 'comment')));
    $body = preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $body));

    if ($need_name && !$user) {
        if ($name === '') {
            return [$name, $body, 'Please enter your name.'];
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return [$name, $body, 'Name must be between 2 and 100 characters.'];
        }
        if (!preg_match("/^[\p{L}\p{M}' .-]+$/u", $name)) {
            return [$name, $body, 'Name may only contain letters, spaces, apostrophes, dots and hyphens.'];
        }
    }
    if ($body === '') {
        return [$name, $body, 'Please write your comment.'];
    }
    if (mb_strlen($body) < 2 || mb_strlen($body) > COMMENT_MAX_LENGTH) {
        return [$name, $body, 'Comments must be between 2 and ' . COMMENT_MAX_LENGTH . ' characters.'];
    }
    if (preg_match_all('#https?://#i', $body) > 2) {
        return [$name, $body, 'Comments may contain at most 2 links.'];
    }
    return [$name, $body, null];
}

/** Remember that this browser session wrote a guest comment (so it may edit/delete it). */
function remember_guest_comment(int $comment_id): void
{
    start_session();
    $mine = $_SESSION['my_comments'] ?? [];
    $mine[] = $comment_id;
    $_SESSION['my_comments'] = array_slice(array_unique(array_map('intval', $mine)), -200);
}

/** True when the current visitor wrote $comment and may edit or delete it. */
function can_manage_comment(array $comment): bool
{
    start_session();
    if (!empty($comment['deleted_at'])) {
        return false;
    }
    if (!empty($comment['user_id'])) {
        $user = current_user();
        return $user !== null && (int) $user['id'] === (int) $comment['user_id'];
    }
    return in_array((int) $comment['id'], array_map('intval', $_SESSION['my_comments'] ?? []), true);
}

/** True when a signed-in admin is viewing (may delete any comment for moderation). */
function is_admin_viewer(): bool
{
    start_session();
    return !empty($_SESSION['admin_logged_in']);
}

/** One comment row (or null) that belongs to $post_id. */
function find_comment(PDO $pdo, int $comment_id, int $post_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE id = :id AND post_id = :post LIMIT 1');
    $stmt->execute([':id' => $comment_id, ':post' => $post_id]);
    return $stmt->fetch() ?: null;
}

/**
 * Delete a comment.
 *  - No replies: the row is removed.
 *  - Has replies: the text is wiped and it is marked deleted, so the replies stay visible.
 * Afterwards, a deleted placeholder that no longer has replies is cleaned up too.
 */
function delete_comment(PDO $pdo, array $comment): void
{
    $id = (int) $comment['id'];
    $has_replies = (int) $pdo->query('SELECT COUNT(*) FROM comments WHERE parent_id = ' . $id)->fetchColumn() > 0;

    if ($has_replies) {
        $pdo->prepare("UPDATE comments SET comment = '', deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
        return;
    }
    $pdo->prepare('DELETE FROM comments WHERE id = :id')->execute([':id' => $id]);

    // Walk up: remove deleted placeholders that are now empty
    $parent_id = (int) ($comment['parent_id'] ?? 0);
    while ($parent_id > 0) {
        $stmt = $pdo->prepare('SELECT id, parent_id, deleted_at FROM comments WHERE id = :id');
        $stmt->execute([':id' => $parent_id]);
        $parent = $stmt->fetch();
        if (!$parent || $parent['deleted_at'] === null) {
            break;
        }
        $left = (int) $pdo->query('SELECT COUNT(*) FROM comments WHERE parent_id = ' . (int) $parent['id'])->fetchColumn();
        if ($left > 0) {
            break;
        }
        $pdo->prepare('DELETE FROM comments WHERE id = :id')->execute([':id' => (int) $parent['id']]);
        $parent_id = (int) $parent['parent_id'];
    }
}

/**
 * All comments of a post as a tree.
 * Returns ['roots' => [...], 'children' => [parent_id => [...]], 'by_id' => [...], 'count' => visible comments].
 * Top-level comments: newest first. Replies: oldest first (reads like a conversation).
 */
function get_comment_tree(PDO $pdo, int $post_id): array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, u.full_name AS user_full_name, u.avatar AS user_avatar
         FROM comments c LEFT JOIN users u ON u.id = c.user_id
         WHERE c.post_id = :post ORDER BY c.created_at ASC, c.id ASC'
    );
    $stmt->execute([':post' => $post_id]);

    $tree = ['roots' => [], 'children' => [], 'by_id' => [], 'count' => 0];
    foreach ($stmt->fetchAll() as $c) {
        $tree['by_id'][(int) $c['id']] = $c;
        if ($c['deleted_at'] === null) {
            $tree['count']++;
        }
    }
    foreach ($tree['by_id'] as $id => $c) {
        $parent = (int) $c['parent_id'];
        if ($parent && isset($tree['by_id'][$parent])) {
            $tree['children'][$parent][] = $c;
        } else {
            $tree['roots'][] = $c;
        }
    }
    $tree['roots'] = array_reverse($tree['roots']);
    return $tree;
}

/** Comment form fields shared by the main form, reply forms and the JS reply template. */
function comment_form_fields(?array $user, string $id_prefix, string $name_value = '', string $body_value = '', string $placeholder = 'Write your comment...'): string
{
    $html = csrf_field()
        . '<div class="hp-field" aria-hidden="true"><label for="' . $id_prefix . '-website">Website</label>'
        . '<input type="text" name="website" id="' . $id_prefix . '-website" tabindex="-1" autocomplete="off"></div>';

    if ($user) {
        $html .= '<p class="comment-as">' . user_avatar_html($user, '', 'avatar avatar-xs') . ' Commenting as <strong>'
            . sanitize($user['full_name']) . '</strong></p>';
    } else {
        $html .= '<div class="form-group"><label for="' . $id_prefix . '-name" class="form-label">Your Name</label>'
            . '<input type="text" name="name" id="' . $id_prefix . '-name" class="form-control" placeholder="e.g. Kelvin Massawe"'
            . ' required minlength="2" maxlength="100" value="' . sanitize($name_value) . '"></div>';
    }
    $html .= '<div class="form-group"><label for="' . $id_prefix . '-text" class="form-label visually-hidden">Comment</label>'
        . '<textarea name="comment" id="' . $id_prefix . '-text" class="form-control" placeholder="' . sanitize($placeholder) . '"'
        . ' required minlength="2" maxlength="' . COMMENT_MAX_LENGTH . '">' . sanitize($body_value) . '</textarea></div>';
    return $html;
}

/**
 * Render one comment with its replies (recursive).
 * $ctx: post_id, user, reply_to (open reply form id), edit_id (open edit form id), old (re-filled values after an error)
 */
function render_comment(array $c, array $tree, int $depth, array $ctx): string
{
    $id = (int) $c['id'];
    $post_id = (int) $ctx['post_id'];
    $deleted = $c['deleted_at'] !== null;
    $mine = can_manage_comment($c);
    $admin = is_admin_viewer();
    $name = $c['user_full_name'] ?: $c['name'];
    $base = 'post.php?id=' . $post_id;

    $avatar = $c['user_id'] && !$deleted
        ? user_avatar_html(['full_name' => $name, 'avatar' => $c['user_avatar']], '', 'avatar comment-avatar-img')
        : '<div class="comment-avatar"><i class="fa-solid ' . ($deleted ? 'fa-comment-slash' : 'fa-user') . '"></i></div>';

    $html = '<article class="comment-item' . ($deleted ? ' is-deleted' : '') . ($mine ? ' is-mine' : '') . '" id="comment-' . $id . '" data-comment-id="' . $id . '">'
        . $avatar . '<div class="comment-content">';

    if ($deleted) {
        $html .= '<p class="comment-text comment-deleted-text"><i class="fa-solid fa-trash-can"></i> This comment was deleted.</p>';
    } else {
        $html .= '<div class="comment-meta"><span class="comment-author">' . sanitize($name) . '</span>';
        if ($c['user_id']) {
            $html .= '<span class="comment-badge" title="Signed-in student"><i class="fa-solid fa-circle-check"></i> Student</span>';
        }
        if ($mine) {
            $html .= '<span class="comment-badge comment-badge-you">You</span>';
        }
        $html .= '<span class="comment-date"><i class="fa-regular fa-clock"></i> '
            . date('M d, Y \a\t H:i', strtotime($c['created_at'])) . '</span>';
        if ($c['updated_at']) {
            $html .= '<span class="comment-edited" title="Edited ' . sanitize(date('M d, Y H:i', strtotime($c['updated_at']))) . '">(edited)</span>';
        }
        $html .= '</div>';

        // Deep replies keep their indent but say who they answer
        $parent = $c['parent_id'] ? ($tree['by_id'][(int) $c['parent_id']] ?? null) : null;
        if ($parent && $depth >= COMMENT_MAX_DEPTH) {
            $pname = $parent['deleted_at'] ? 'a deleted comment' : ($parent['user_full_name'] ?: $parent['name']);
            $html .= '<a class="comment-replying-to" href="#comment-' . (int) $parent['id'] . '"><i class="fa-solid fa-reply"></i> Replying to ' . sanitize($pname) . '</a>';
        }

        $editing = $mine && (int) $ctx['edit_id'] === $id;
        $html .= '<p class="comment-text"' . ($editing ? ' hidden' : '') . '>' . nl2br(sanitize($c['comment'])) . '</p>';

        // Edit form: only rendered for the author
        if ($mine) {
            $value = $editing && isset($ctx['old']['edit_body']) ? $ctx['old']['edit_body'] : $c['comment'];
            $html .= '<form action="' . $base . '#comment-' . $id . '" method="POST" class="comment-edit-form"' . ($editing ? '' : ' hidden') . '>'
                . csrf_field()
                . '<input type="hidden" name="action" value="edit_comment"><input type="hidden" name="comment_id" value="' . $id . '">'
                . '<label for="edit-' . $id . '" class="visually-hidden">Edit your comment</label>'
                . '<textarea name="comment" id="edit-' . $id . '" class="form-control" required minlength="2" maxlength="' . COMMENT_MAX_LENGTH . '">' . sanitize($value) . '</textarea>'
                . '<div class="comment-form-actions">'
                . '<button type="submit" class="btn btn-primary btn-sm" data-loading-text="Saving..."><i class="fa-solid fa-floppy-disk"></i> Save</button>'
                . '<a href="' . $base . '#comment-' . $id . '" class="btn btn-outline btn-sm" data-comment-cancel>Cancel</a>'
                . '</div></form>';
        }

        // Actions: Reply (everyone), Edit / Delete (author only), Delete (admin moderation)
        $html .= '<div class="comment-actions">'
            . '<a href="' . $base . '&amp;reply=' . $id . '#reply-' . $id . '" class="comment-action" data-reply-to="' . $id . '" data-reply-name="' . sanitize($name) . '">'
            . '<i class="fa-solid fa-reply"></i> Reply</a>';
        if ($mine) {
            $html .= '<a href="' . $base . '&amp;edit=' . $id . '#comment-' . $id . '" class="comment-action" data-edit-comment="' . $id . '"><i class="fa-solid fa-pen"></i> Edit</a>';
        }
        if ($mine || $admin) {
            $html .= '<form action="' . $base . '#comments" method="POST" class="inline-form">' . csrf_field()
                . '<input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="' . $id . '">'
                . '<button type="submit" class="comment-action comment-action-danger" data-confirm="'
                . ($mine ? 'Delete your comment? This cannot be undone.' : 'Delete this comment as administrator?') . '">'
                . '<i class="fa-solid fa-trash-can"></i> Delete' . ($mine ? '' : ' (admin)') . '</button></form>';
        }
        $html .= '</div>';

        // Reply form opened without JavaScript (?reply=ID). With JavaScript it is inserted on click.
        if ((int) $ctx['reply_to'] === $id) {
            $html .= '<form action="' . $base . '#comment-' . $id . '" method="POST" class="comment-reply-form" id="reply-' . $id . '">'
                . '<input type="hidden" name="action" value="add_comment"><input type="hidden" name="parent_id" value="' . $id . '">'
                . comment_form_fields($ctx['user'], 'reply-' . $id, $ctx['old']['name'] ?? '', $ctx['old']['reply_body'] ?? '', 'Reply to ' . $name . '...')
                . '<div class="comment-form-actions"><button type="submit" class="btn btn-primary btn-sm" data-loading-text="Posting..."><i class="fa-solid fa-paper-plane"></i> Post Reply</button>'
                . '<a href="' . $base . '#comment-' . $id . '" class="btn btn-outline btn-sm">Cancel</a></div></form>';
        }
    }

    // Replies
    $replies = $tree['children'][$id] ?? [];
    if ($replies) {
        $html .= '<div class="comment-replies' . ($depth >= COMMENT_MAX_DEPTH ? ' is-flat' : '') . '">';
        foreach ($replies as $reply) {
            $html .= render_comment($reply, $tree, min($depth + 1, COMMENT_MAX_DEPTH), $ctx);
        }
        $html .= '</div>';
    }

    return $html . '</div></article>';
}
