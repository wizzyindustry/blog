<?php
/**
 * Load-test data generator (command line only).
 *
 *   php sql/seed_load_test.php            Add 10,000 demo users, 10,000 files and ~30,000 sign-ins
 *   php sql/seed_load_test.php 20000      Choose how many users / files to add
 *   php sql/seed_load_test.php --clean    Remove every demo user (and their rows)
 *
 * Demo users have usernames starting with "demo_" and the password "Password123".
 * Their file rows are metadata only (no file on disk), so they show up in lists,
 * counts and charts, but downloading one says "File not found".
 *
 * Rows are written in multi-row INSERT batches inside transactions, which is how
 * the schema is expected to absorb large imports quickly.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$start = microtime(true);

if (in_array('--clean', $argv, true)) {
    $ids = $pdo->query("SELECT id FROM users WHERE username LIKE 'demo\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 1000) as $chunk) {
        $in = implode(',', array_map('intval', $chunk));
        $pdo->exec("DELETE FROM users WHERE id IN ($in)"); // files + logins cascade
    }
    printf("Removed %d demo users in %.1fs\n", count($ids), microtime(true) - $start);
    exit;
}

$count = max(1, (int) ($argv[1] ?? 10000));
$batch = 1000;
$password = password_hash('Password123', PASSWORD_DEFAULT);

$first = ['Neema', 'Baraka', 'Zainab', 'Kelvin', 'Grace', 'Emmanuel', 'Asha', 'Juma', 'Rehema', 'Daudi', 'Halima', 'Peter', 'Mariam', 'John', 'Fatuma', 'Joseph', 'Upendo', 'Ali', 'Esther', 'Hamisi'];
$last = ['Mwita', 'Joseph', 'Rashid', 'Massawe', 'Makau', 'Shija', 'Mollel', 'Mushi', 'Kimaro', 'Mrema', 'Lyimo', 'Swai', 'Komba', 'Ngowi', 'Mbwambo', 'Temba'];
$kinds = [
    'image' => ['jpg', 'png', 'webp'], 'document' => ['pdf', 'docx', 'pptx', 'xlsx'], 'video' => ['mp4'],
    'audio' => ['mp3'], 'archive' => ['zip'], 'code' => ['py', 'java', 'sql'], 'other' => ['bin'],
];
$kind_weights = ['document' => 45, 'image' => 30, 'video' => 6, 'audio' => 4, 'archive' => 7, 'code' => 6, 'other' => 2];

function weighted(array $weights): string
{
    $r = mt_rand(1, array_sum($weights));
    foreach ($weights as $key => $w) {
        if (($r -= $w) <= 0) {
            return $key;
        }
    }
    return array_key_first($weights);
}

/** Random datetime within the last $days days, biased toward recent days (growth curve). */
function recent_date(int $days): string
{
    $age = (int) floor($days * (1 - sqrt(mt_rand() / mt_getrandmax())));
    return date('Y-m-d H:i:s', strtotime("-$age days") - mt_rand(0, 86399));
}

$run = bin2hex(random_bytes(3)); // Keeps usernames unique if the script is run twice

// ---------- Users ----------
$user_ids = [];
for ($done = 0; $done < $count; $done += $batch) {
    $n = min($batch, $count - $done);
    $rows = [];
    $params = [];
    for ($i = 0; $i < $n; $i++) {
        $k = $done + $i;
        $name = $first[array_rand($first)] . ' ' . $last[array_rand($last)];
        $created = recent_date(120);
        $seen = mt_rand(1, 100) <= 85 ? date('Y-m-d H:i:s', max(strtotime($created), time() - mt_rand(0, 45 * 86400))) : null;
        if ($seen && mt_rand(1, 100) <= 2) {
            $seen = date('Y-m-d H:i:s', time() - mt_rand(0, 240)); // a few "online now"
        }
        $rows[] = '(?,?,?,?,?,?,?)';
        array_push($params, $name, "demo_{$run}_$k", "demo_{$run}_$k@example.com", $password, $created, $seen, $seen);
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO users (full_name, username, email, password, created_at, last_seen_at, last_login_at) VALUES ' . implode(',', $rows))
        ->execute($params);
    $first_id = (int) $pdo->lastInsertId(); // First id of a multi-row insert
    $pdo->commit();
    $user_ids = array_merge($user_ids, range($first_id, $first_id + $n - 1));
    printf("\rUsers: %d / %d", $done + $n, $count);
}
echo "\n";

// ---------- Files (metadata only) ----------
for ($done = 0; $done < $count; $done += $batch) {
    $n = min($batch, $count - $done);
    $rows = [];
    $params = [];
    for ($i = 0; $i < $n; $i++) {
        $kind = weighted($kind_weights);
        $ext = $kinds[$kind][array_rand($kinds[$kind])];
        $size = $kind === 'video' ? mt_rand(5e6, 9e7) : ($kind === 'image' ? mt_rand(8e4, 4e6) : mt_rand(2e4, 8e6));
        $rows[] = '(?,?,?,?,?,?,?,?)';
        array_push($params, $user_ids[array_rand($user_ids)], "Sample file " . ($done + $i + 1) . ".$ext",
            'uf_seed_' . $run . '_' . ($done + $i), $ext, $kind, $size, mt_rand(0, 40), recent_date(90));
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO user_files (user_id, original_name, file_name, extension, file_kind, file_size, download_count, created_at) VALUES ' . implode(',', $rows))
        ->execute($params);
    $pdo->commit();
    printf("\rFiles: %d / %d", $done + $n, $count);
}
echo "\n";

// Keep the per-user counters in sync with the inserted files
$pdo->exec(
    "UPDATE users u JOIN (SELECT user_id, COUNT(*) n, SUM(file_size) b FROM user_files GROUP BY user_id) f ON f.user_id = u.id
     SET u.file_count = f.n, u.storage_used = f.b WHERE u.username LIKE 'demo\\_%'"
);

// ---------- Sign-ins (3 per user on average, spread over 90 days, daytime-heavy) ----------
$logins = $count * 3;
for ($done = 0; $done < $logins; $done += $batch) {
    $n = min($batch, $logins - $done);
    $rows = [];
    $params = [];
    for ($i = 0; $i < $n; $i++) {
        $day = date('Y-m-d', strtotime(recent_date(90)));
        $hour = weighted([7 => 3, 8 => 6, 9 => 8, 10 => 9, 11 => 8, 12 => 5, 13 => 6, 14 => 8, 15 => 8, 16 => 7, 17 => 6, 18 => 5, 19 => 6, 20 => 7, 21 => 6, 22 => 4, 23 => 2, 0 => 1, 6 => 2]);
        $rows[] = '(?,?,?,?)';
        array_push($params, $user_ids[array_rand($user_ids)], '127.0.0.1', 'Mozilla/5.0 (Load test)',
            sprintf('%s %02d:%02d:%02d', $day, $hour, mt_rand(0, 59), mt_rand(0, 59)));
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO user_logins (user_id, ip_address, user_agent, created_at) VALUES ' . implode(',', $rows))->execute($params);
    $pdo->commit();
    printf("\rSign-ins: %d / %d", $done + $n, $logins);
}
echo "\n";

printf("Done in %.1fs. Remove with: php sql/seed_load_test.php --clean\n", microtime(true) - $start);
