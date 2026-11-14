<?php
/**
 * Outgoing email (no libraries): a small SMTP client with TLS, plus a development fallback.
 * UDOM Student Materials Blog
 *
 * Settings (config/secrets.php or environment):
 *   SMTP_HOST, SMTP_PORT (587), SMTP_SECURE ('tls' = STARTTLS, 'ssl' = implicit TLS on 465, 'none'),
 *   SMTP_USER, SMTP_PASS, MAIL_FROM, MAIL_FROM_NAME
 *
 * When SMTP_HOST is empty (local development) messages are NOT sent: they are saved as .eml files
 * in logs/mail/ (never reachable from the web) so links can be tested. Do not use that on a live site.
 * The SMTP password is never logged; on a live server message bodies are never logged either.
 */

/** 'smtp' when an SMTP server is configured, otherwise 'log'. */
function mail_driver(): string
{
    return trim((string) app_config('SMTP_HOST', '')) !== '' ? 'smtp' : 'log';
}

/** Remove characters that could inject extra mail headers. */
function mail_header_safe(string $value): string
{
    return trim(preg_replace('/[\r\n\t\x00-\x1f\x7f]+/', ' ', $value));
}

/** RFC 2047 encoding for non-ASCII header text (names, subjects). */
function mail_encode_header(string $text): string
{
    $text = mail_header_safe($text);
    return preg_match('/[^\x20-\x7e]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

function mail_from_address(): string
{
    $from = trim((string) app_config('MAIL_FROM', ''));
    if ($from === '' && filter_var((string) app_config('SMTP_USER', ''), FILTER_VALIDATE_EMAIL)) {
        $from = (string) app_config('SMTP_USER');
    }
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'no-reply@localhost';
}

/** Full RFC 5322 message: text + HTML alternatives, base64 encoded. */
function mail_build_message(string $to, string $to_name, string $subject, string $text, string $html): string
{
    $from = mail_from_address();
    $from_name = (string) app_config('MAIL_FROM_NAME', defined('SITE_NAME') ? SITE_NAME : 'UDOM Student Materials');
    $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
    $boundary = 'b_' . bin2hex(random_bytes(12));

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . mail_encode_header($from_name) . ' <' . $from . '>',
        'To: ' . ($to_name !== '' ? mail_encode_header($to_name) . ' ' : '') . '<' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Auto-Submitted: auto-generated',
    ];
    $part = fn(string $type, string $body) => '--' . $boundary . "\r\n"
        . 'Content-Type: ' . $type . '; charset=UTF-8' . "\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n";

    return implode("\r\n", $headers) . "\r\n\r\n"
        . $part('text/plain', $text) . $part('text/html', $html) . '--' . $boundary . "--\r\n";
}

/**
 * Send one email. Returns true when the SMTP server accepted it (or it was saved in log mode).
 * Failures are logged without secrets and never shown to visitors.
 */
function send_mail(string $to, string $to_name, string $subject, string $text, string $html): bool
{
    $to = mail_header_safe($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Mail: invalid recipient address');
        return false;
    }
    $message = mail_build_message($to, mail_header_safe($to_name), $subject, $text, $html);

    if (mail_driver() === 'log') {
        $dir = __DIR__ . '/../logs/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        return @file_put_contents($file, $message, LOCK_EX) !== false;
    }
    return smtp_send(mail_from_address(), $to, $message);
}

/** Read one (possibly multi-line) SMTP reply; returns [code, text]. */
function smtp_read($fp): array
{
    $text = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return [(int) substr($text, 0, 3), trim($text)];
}

/** Send a command and check the reply code. Throws on an unexpected reply. */
function smtp_cmd($fp, ?string $command, array $expect, string $label = ''): string
{
    if ($command !== null) {
        fwrite($fp, $command . "\r\n");
    }
    [$code, $text] = smtp_read($fp);
    if (!in_array($code, $expect, true)) {
        // The label is used instead of the command so credentials never reach the log
        throw new RuntimeException(($label ?: strtok((string) $command, ' ')) . ' -> ' . ($code ?: 'no reply') . ' ' . substr($text, 0, 120));
    }
    return $text;
}

function smtp_send(string $from, string $to, string $message): bool
{
    $host = (string) app_config('SMTP_HOST');
    $secure = strtolower((string) app_config('SMTP_SECURE', 'tls'));
    $port = (int) app_config('SMTP_PORT', $secure === 'ssl' ? 465 : 587);
    $user = (string) app_config('SMTP_USER', '');
    $pass = (string) app_config('SMTP_PASS', '');

    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
        'SNI_enabled' => true,
    ]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log('Mail: cannot connect to SMTP server ' . $host . ':' . $port . ' (' . $errstr . ')');
        return false;
    }
    stream_set_timeout($fp, 20);
    $helo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';

    try {
        smtp_cmd($fp, null, [220], 'greeting');
        $ehlo = smtp_cmd($fp, 'EHLO ' . $helo, [250]);
        if ($secure === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) {
                throw new RuntimeException('server does not offer STARTTLS'); // Never fall back to plain text
            }
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS handshake failed');
            }
            smtp_cmd($fp, 'EHLO ' . $helo, [250]);
        }
        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode($user), [334], 'AUTH user');
            smtp_cmd($fp, base64_encode($pass), [235], 'AUTH password');
        }
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);
        // Dot-stuffing: a line starting with "." gets an extra "."
        fwrite($fp, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
        smtp_cmd($fp, null, [250], 'message');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (RuntimeException $e) {
        error_log('Mail: SMTP error: ' . $e->getMessage());
        @fclose($fp);
        return false;
    }
}

/**
 * Run $job AFTER the page has been delivered to the visitor (used for sending email).
 * SMTP can take several seconds; doing it after the response keeps pages fast and makes
 * "Forgot password" answer in the same time whether or not an email is sent.
 * The page must call start_deferred_output() before printing anything.
 */
function defer_after_response(callable $job): void
{
    if (!isset($GLOBALS['deferred_jobs'])) {
        $GLOBALS['deferred_jobs'] = [];
        register_shutdown_function(function () {
            finish_response_early();
            foreach ($GLOBALS['deferred_jobs'] as $deferred) {
                try {
                    $deferred();
                } catch (Throwable $e) {
                    error_log('Deferred job failed: ' . $e->getMessage());
                }
            }
        });
    }
    $GLOBALS['deferred_jobs'][] = $job;
}

/** Capture the page so it can be delivered (with its exact length) before deferred jobs run. */
function start_deferred_output(): void
{
    ob_start();
}

/** Send the captured page to the visitor and let the browser finish loading. */
function finish_response_early(): void
{
    ignore_user_abort(true); // Keep running after the visitor has their page
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // Release the session lock: the visitor's next click must not wait for the email
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $parts = [];
    while (ob_get_level() > 0) {
        array_unshift($parts, (string) ob_get_clean());
    }
    $body = implode('', $parts);
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1'); // Compression would buffer the page until PHP ends
    }
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    flush();
}

/** Simple branded HTML email around $body_html (already escaped / trusted). */
function mail_html_layout(string $title, string $body_html): string
{
    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><body style="margin:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden">'
        . '<tr><td style="background:#0b1f3a;border-bottom:3px solid #d4af37;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold">UDOM Student Materials</td></tr>'
        . '<tr><td style="padding:24px"><h1 style="margin:0 0 16px;font-size:20px;color:#0b1f3a">' . $e($title) . '</h1>'
        . $body_html . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#f9fafb;color:#6b7280;font-size:12px">This is an automatic message. Please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
