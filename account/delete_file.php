<?php
/**
 * Delete one of the current user's files (POST + CSRF).
 */
require_once __DIR__ . '/auth_check.php';

// Return to the same filtered/paginated list (query string only, never another site)
$return = input_text($_POST, 'return');
$return = preg_match('/^\?[A-Za-z0-9_=&%.+\-]*$/', $return) ? $return : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('error', 'Your session expired. Please try again.');
    header('Location: files.php' . $return);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM user_files WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => (int) input_text($_POST, 'id', '0'), ':uid' => (int) $user['id']]);
$file = $stmt->fetch();

if (!$file) {
    flash('error', 'That file was not found.');
} else {
    try {
        delete_user_file($pdo, $file);
        flash('success', '"' . $file['original_name'] . '" was deleted.');
    } catch (PDOException $e) {
        flash('error', 'The file could not be deleted. Please try again.');
    }
}

header('Location: files.php' . $return);
exit;
