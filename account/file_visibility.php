<?php
/**
 * Quick switch of one file between Private and Public (POST + CSRF).
 */
require_once __DIR__ . '/auth_check.php';

$return = input_text($_POST, 'return');
$return = preg_match('/^\?[A-Za-z0-9_=&%.+\-]*$/', $return) ? $return : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('error', 'Your session expired. Please try again.');
    header('Location: files.php' . $return);
    exit;
}

$visibility = input_text($_POST, 'visibility') === 'public' ? 'public' : 'private';
$stmt = $pdo->prepare('SELECT id, original_name, title FROM user_files WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => (int) input_text($_POST, 'id', '0'), ':uid' => (int) $user['id']]);
$file = $stmt->fetch();

if (!$file) {
    flash('error', 'That file was not found.');
} else {
    $pdo->prepare('UPDATE user_files SET visibility = :v WHERE id = :id')->execute([':v' => $visibility, ':id' => (int) $file['id']]);
    flash('success', '"' . user_file_title($file) . '" is now '
        . ($visibility === 'public' ? 'public and shown on the Materials page.' : 'private. Only you can see it.'));
}

header('Location: files.php' . $return);
exit;
