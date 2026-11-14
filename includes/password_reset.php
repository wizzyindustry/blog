<?php
/**
 * Password reset by email
 * UDOM Student Materials Blog
 *
 *  - The emailed link carries a 256-bit random token; only its SHA-256 hash is stored.
 *  - Links expire after RESET_TOKEN_TTL_MINUTES and work once. A new request cancels older links.
 *  - The same message is shown whether or not the email belongs to an account (no account discovery),
 *    and every request takes about the same time.
 *  - Links are built from APP_URL. Without APP_URL they are only built for localhost, so a forged
 *    "Host:" header cannot make the site email a link that points to an attacker's server.
 *  - After a reset, every other signed-in device is signed out (users.password_changed_at).
 */

const RESET_TOKEN_TTL_MINUTES = 30;
const RESET_REQUESTS_PER_IP = 5;          // per 15 minutes
const RESET_REQUESTS_PER_ACCOUNT = 3;     // per hour
const RESET_MIN_RESPONSE_SECONDS = 2.0;   // same response time whether or not an email is sent

function reset_token_hash(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Base URL for links in emails, or null when it cannot be trusted
 * (no APP_URL and the request did not come to localhost).
 */
function reset_link_base(): ?string
{
    if (trim((string) app_config('APP_URL', '')) !== '') {
        return site_url('');
    }
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) ? site_url('') : null;
}

/** Create a reset link for $user (cancels older ones). Returns the plain token, or null. */
function create_password_reset(PDO $pdo, array $user): ?string
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :u AND used_at IS NULL')
            ->execute([':u' => (int) $user['id']]);
        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, ip_address, expires_at)
             VALUES (:u, :h, :ip, NOW() + INTERVAL ' . RESET_TOKEN_TTL_MINUTES . ' MINUTE)'
        )->execute([':u' => (int) $user['id'], ':h' => reset_token_hash($token), ':ip' => client_ip()]);
        // Keep the table small: forget links that ended more than a day ago
        $pdo->exec('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY');
        $pdo->commit();
        return $token;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Password reset: could not store token: ' . $e->getMessage());
        return null;
    }
}

/** The valid (unused, unexpired, active-account) reset row for a token hash, or null. */
function find_password_reset(PDO $pdo, string $token_hash): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token_hash)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT r.id AS reset_id, r.expires_at, u.id, u.full_name, u.username, u.email, u.status
         FROM password_resets r JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = :h AND r.used_at IS NULL AND r.expires_at > NOW() AND u.status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([':h' => $token_hash]);
    return $stmt->fetch() ?: null;
}

/** Email the reset link. */
function send_password_reset_email(array $user, string $link): bool
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $minutes = RESET_TOKEN_TTL_MINUTES;
    $text = "Hello {$user['full_name']},\n\n"
        . "Someone (hopefully you) asked to reset the password for your UDOM Student Materials account ({$user['username']}).\n\n"
        . "Open this link to choose a new password:\n{$link}\n\n"
        . "The link works once and expires in {$minutes} minutes.\n\n"
        . "If you did not ask for this, ignore this email: your password stays the same.\n";
    $html = mail_html_layout('Reset your password',
        '<p>Hello ' . $e($user['full_name']) . ',</p>'
        . '<p>Someone (hopefully you) asked to reset the password for your account <strong>' . $e($user['username']) . '</strong>.</p>'
        . '<p style="margin:24px 0"><a href="' . $e($link) . '" style="background:#0b1f3a;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">Choose a new password</a></p>'
        . '<p style="font-size:13px;color:#6b7280">The link works once and expires in ' . $minutes . ' minutes. If the button does not work, copy this address into your browser:<br><span style="word-break:break-all">' . $e($link) . '</span></p>'
        . '<p style="font-size:13px;color:#6b7280">If you did not ask for this, ignore this email: your password stays the same.</p>');
    return send_mail((string) $user['email'], (string) $user['full_name'], 'Reset your UDOM Student Materials password', $text, $html);
}

/** Tell the owner that the password was changed (so an unexpected change is noticed). */
function send_password_changed_email(array $user): bool
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $when = date('M j, Y \a\t H:i');
    $text = "Hello {$user['full_name']},\n\nThe password for your UDOM Student Materials account ({$user['username']}) was changed on {$when} (East Africa Time).\n"
        . "All other devices were signed out.\n\nIf this was not you, reset your password straight away using \"Forgot password?\" on the sign-in page, and contact the site administrator.\n";
    $html = mail_html_layout('Your password was changed',
        '<p>Hello ' . $e($user['full_name']) . ',</p>'
        . '<p>The password for your account <strong>' . $e($user['username']) . '</strong> was changed on ' . $e($when) . ' (East Africa Time). All other devices were signed out.</p>'
        . '<p>If this was not you, reset your password straight away using <strong>Forgot password?</strong> on the sign-in page, and contact the site administrator.</p>');
    return send_mail((string) $user['email'], (string) $user['full_name'], 'Your UDOM Student Materials password was changed', $text, $html);
}
