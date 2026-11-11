<?php
/**
 * Admin actions on users and their files (POST + CSRF only).
 *   action=block | unblock | delete_user   (id = user id)
 *   action=delete_file | hide_file         (id = user_files id; hide_file makes a shared file private)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

// Return to an admin page in this folder only (never an outside URL)
$return = input_text($_POST, 'return', 'users.php');
if (!preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $return)) {
    $return = 'users.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('error', 'Your session expired. Please try again.');
    header('Location: ' . $return);
    exit;
}

$action = input_text($_POST, 'action');
$id = (int) input_text($_POST, 'id', '0');

try {
    if ($action === 'hide_file') {
        // Moderation: take a shared file off the Materials page without deleting it
        $stmt = $pdo->prepare("UPDATE user_files SET visibility = 'private' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount()
            ? 'The file was made private and removed from the Materials page.'
            : 'That file was not found or is already private.');
    } elseif ($action === 'delete_file') {
        $stmt = $pdo->prepare('SELECT * FROM user_files WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $file = $stmt->fetch();
        if ($file) {
            delete_user_file($pdo, $file);
            flash('success', 'File "' . $file['original_name'] . '" was deleted.');
        } else {
            flash('error', 'That file no longer exists.');
        }
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            flash('error', 'That user no longer exists.');
        } elseif ($action === 'block' || $action === 'unblock') {
            $pdo->prepare('UPDATE users SET status = :s WHERE id = :id')
                ->execute([':s' => $action === 'block' ? 'blocked' : 'active', ':id' => $id]);
            flash('success', $target['full_name'] . ($action === 'block' ? ' was blocked.' : ' was unblocked.'));
        } elseif ($action === 'delete_user') {
            delete_user_account($pdo, $id);
            flash('success', $target['full_name'] . ' and all their files were deleted.');
            if (strpos($return, 'user.php') === 0) {
                $return = 'users.php';
            }
        } else {
            flash('error', 'Unknown action.');
        }
    }
} catch (PDOException $e) {
    flash('error', 'The action could not be completed. Please try again.');
}

header('Location: ' . $return);
exit;
