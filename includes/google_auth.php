<?php
/**
 * Sign in with Google (OAuth 2.0 authorization code flow + OpenID Connect)
 * UDOM Student Materials Blog
 *
 *  - Server-side redirect flow only: no Google JavaScript, nothing inline (fits the CSP).
 *  - state (stops login CSRF), nonce (stops ID-token replay) and PKCE S256 (stops stolen-code use).
 *  - The ID token's RS256 signature is verified HERE against Google's published keys (JWKS),
 *    then iss, aud, exp/iat, nonce and email_verified are checked.
 *  - Only Google's permanent account ID ("sub") is stored. Access / refresh tokens are discarded.
 *  - An existing account is NEVER linked just because the email matches (account-takeover risk):
 *    the owner signs in with their password and connects Google under Password & Security.
 *
 * Settings (config/secrets.php or environment): GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI.
 * When any of them is empty, Google sign-in is switched off and its buttons are hidden.
 */

const GOOGLE_AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
const GOOGLE_JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';
const GOOGLE_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];
const GOOGLE_CLOCK_SKEW = 60;          // Seconds of clock difference tolerated for exp / iat
const GOOGLE_FLOW_TTL = 600;           // A started sign-in must come back within 10 minutes
const GOOGLE_PENDING_TTL = 600;        // "Finish creating your account" step is valid for 10 minutes
const GOOGLE_START_LIMIT = 20;         // Sign-in starts per IP ...
const GOOGLE_START_WINDOW = 15;        // ... per 15 minutes

/** True when Google sign-in is fully configured. */
function google_enabled(): bool
{
    return trim((string) app_config('GOOGLE_CLIENT_ID', '')) !== ''
        && trim((string) app_config('GOOGLE_CLIENT_SECRET', '')) !== ''
        && trim((string) app_config('GOOGLE_REDIRECT_URI', '')) !== '';
}

function google_b64url_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/** Strict base64url decode; false on invalid input. */
function google_b64url_decode(string $text)
{
    if ($text === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $text)) {
        return false;
    }
    $text = strtr($text, '-_', '+/');
    $pad = strlen($text) % 4;
    if ($pad === 1) {
        return false;
    }
    if ($pad) {
        $text .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($text, true);
}

/**
 * Start a Google sign-in: create state, nonce and PKCE verifier, keep them in the session
 * and return the URL to send the browser to.
 * $mode 'login' (sign in / sign up) or 'link' (connect Google to the signed-in account).
 * $return: a safe local path to go to afterwards ('' = dashboard).
 */
function google_auth_url(string $mode = 'login', string $return = ''): string
{
    start_session();
    $state = google_b64url_encode(random_bytes(32));
    $nonce = google_b64url_encode(random_bytes(32));
    $verifier = google_b64url_encode(random_bytes(48)); // 64 characters (RFC 7636: 43-128)

    $_SESSION['google_oauth'] = [
        'state' => $state,
        'nonce' => $nonce,
        'verifier' => $verifier,
        'mode' => $mode === 'link' ? 'link' : 'login',
        'user_id' => $mode === 'link' ? (int) ($_SESSION['user_id'] ?? 0) : 0,
        'return' => is_safe_local_path($return) ? $return : '',
        'started' => time(),
    ];

    return GOOGLE_AUTH_ENDPOINT . '?' . http_build_query([
        'client_id' => (string) app_config('GOOGLE_CLIENT_ID'),
        'redirect_uri' => (string) app_config('GOOGLE_REDIRECT_URI'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => google_b64url_encode(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
        'access_type' => 'online', // No refresh token
    ], '', '&', PHP_QUERY_RFC3986);
}

const GOOGLE_STATE_FAIL_LIMIT = 10;      // Failed callbacks per IP ...
const GOOGLE_STATE_FAIL_WINDOW = 15;     // ... per 15 minutes, then HTTP 429

/**
 * STRICT check of a callback from Google, done before anything else (also for "error" replies).
 * Returns null when the callback belongs to the sign-in THIS browser started, otherwise the reason.
 *
 *  - The session must hold a started flow (it is removed by the caller: one use only).
 *  - Exactly one "state" parameter, in the exact format we generate (43 base64url characters).
 *  - Constant-time comparison with the stored value; the flow must be under 10 minutes old.
 *  - GET only; "code" and "error" must not both be present.
 *  - If Google sends "iss" (RFC 9207), it must be Google's issuer.
 *  - Link mode: the same student must still be signed in.
 */
function google_check_callback_state($flow, array $query, string $raw_query, string $method): ?string
{
    if ($method !== 'GET') {
        return 'method ' . $method;
    }
    if (!is_array($flow) || !is_string($flow['state'] ?? null) || !is_int($flow['started'] ?? null)) {
        return 'no sign-in was started in this browser session';
    }
    // Exactly one state parameter (PHP would silently keep the last of "state=a&state=b")
    if (preg_match_all('/(?:^|&)state(?:%5B[^=&]*%5D|\[[^=&]*\])?=/i', $raw_query) !== 1) {
        return 'state parameter missing or repeated';
    }
    $state = $query['state'] ?? null;
    if (!is_string($state) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $state)) {
        return 'state parameter malformed';
    }
    if (!hash_equals($flow['state'], $state)) {
        return 'state does not match';
    }
    if (time() - $flow['started'] > GOOGLE_FLOW_TTL || $flow['started'] > time() + 5) {
        return 'state expired';
    }
    if (isset($query['code']) && isset($query['error'])) {
        return 'both code and error present';
    }
    if (isset($query['iss']) && !in_array($query['iss'], GOOGLE_ISSUERS, true)) {
        return 'unexpected issuer';
    }
    if (($flow['mode'] ?? '') === 'link' && (int) ($_SESSION['user_id'] ?? 0) !== (int) ($flow['user_id'] ?? -1)) {
        return 'signed-in account changed during the connect flow';
    }
    return null;
}

/**
 * HTTPS request to Google. Returns ['status' => int, 'body' => string, 'max_age' => ?int] or null on network error.
 * TLS certificates are always verified; only HTTPS is allowed.
 */
function google_http(string $url, ?array $post = null): ?array
{
    if (!function_exists('curl_init')) {
        error_log('Google sign-in: the PHP curl extension is not enabled');
        return null;
    }
    $max_age = null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'UDOM-Student-Materials/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$max_age) {
            if (preg_match('/^cache-control:.*\bmax-age=(\d+)/i', $line, $m)) {
                $max_age = (int) $m[1];
            }
            return strlen($line);
        },
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('Google sign-in: request to ' . parse_url($url, PHP_URL_HOST) . ' failed: ' . $error);
        return null;
    }
    return ['status' => $status, 'body' => (string) $body, 'max_age' => $max_age];
}

/**
 * Exchange the authorization code for tokens. Returns ONLY the ID token (the access token is
 * dropped immediately and never stored or logged), or null on failure.
 */
function google_exchange_code(string $code, string $verifier): ?string
{
    $res = google_http(GOOGLE_TOKEN_ENDPOINT, [
        'code' => $code,
        'client_id' => (string) app_config('GOOGLE_CLIENT_ID'),
        'client_secret' => (string) app_config('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => (string) app_config('GOOGLE_REDIRECT_URI'),
        'grant_type' => 'authorization_code',
        'code_verifier' => $verifier,
    ]);
    if ($res === null) {
        return null;
    }
    $json = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !is_array($json) || !is_string($json['id_token'] ?? null)) {
        // Log only Google's error code, never the response body (it may contain tokens)
        $err = is_array($json) && is_string($json['error'] ?? null) ? $json['error'] : 'unexpected response';
        error_log('Google sign-in: token exchange failed (HTTP ' . $res['status'] . ', ' . preg_replace('/[^a-z_]/i', '', $err) . ')');
        return null;
    }
    return $json['id_token'];
}

/** Google's public signing keys, cached on disk for as long as Google allows (Cache-Control max-age). */
function google_jwks(bool $force_refresh = false): array
{
    $cache = __DIR__ . '/../logs/google_jwks.json'; // logs/ is not reachable from the web
    if (!$force_refresh && is_file($cache)) {
        $data = json_decode((string) @file_get_contents($cache), true);
        if (is_array($data) && ($data['expires'] ?? 0) > time() && is_array($data['keys'] ?? null)) {
            return $data['keys'];
        }
    }
    $res = google_http(GOOGLE_JWKS_URI);
    $json = $res ? json_decode($res['body'], true) : null;
    if (!$res || $res['status'] !== 200 || !is_array($json['keys'] ?? null)) {
        error_log('Google sign-in: could not download Google signing keys');
        return [];
    }
    $ttl = max(300, min(86400, (int) ($res['max_age'] ?? 3600)));
    @file_put_contents($cache, json_encode(['expires' => time() + $ttl, 'keys' => $json['keys']]), LOCK_EX);
    return $json['keys'];
}

/** DER length / INTEGER helpers for building an RSA public key. */
function google_asn1_length(int $length): string
{
    if ($length < 0x80) {
        return chr($length);
    }
    $bytes = ltrim(pack('N', $length), "\0");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function google_asn1_integer(string $bytes): string
{
    $bytes = ltrim($bytes, "\0");
    if ($bytes === '' || ord($bytes[0]) > 0x7f) {
        $bytes = "\0" . $bytes; // Keep it positive
    }
    return "\x02" . google_asn1_length(strlen($bytes)) . $bytes;
}

/** PEM public key from an RSA JWK (n, e), or null. */
function google_jwk_to_pem(array $jwk): ?string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || !is_string($jwk['n'] ?? null) || !is_string($jwk['e'] ?? null)) {
        return null;
    }
    $n = google_b64url_decode($jwk['n']);
    $e = google_b64url_decode($jwk['e']);
    if ($n === false || $e === false || strlen(ltrim($n, "\0")) < 256) { // At least 2048-bit keys
        return null;
    }
    $rsa = google_asn1_integer($n) . google_asn1_integer($e);
    $rsa = "\x30" . google_asn1_length(strlen($rsa)) . $rsa;
    $bit_string = "\x03" . google_asn1_length(strlen($rsa) + 1) . "\x00" . $rsa;
    $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption, NULL
    $spki = "\x30" . google_asn1_length(strlen($algorithm . $bit_string)) . $algorithm . $bit_string;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Verify a Google ID token and return its claims, or null (the reason is logged, the token is not).
 * $jwks / $now can be passed by tests; normally Google's live keys and the current time are used.
 */
function google_verify_id_token(string $jwt, string $nonce, ?array $jwks = null, ?int $now = null): ?array
{
    $fail = function (string $why): ?array {
        error_log('Google sign-in: ID token rejected (' . $why . ')');
        return null;
    };
    $now = $now ?? time();

    $parts = explode('.', $jwt);
    if (count($parts) !== 3 || strlen($jwt) > 8192) {
        return $fail('malformed');
    }
    [$h64, $p64, $s64] = $parts;
    $header = json_decode((string) google_b64url_decode($h64), true);
    $claims = json_decode((string) google_b64url_decode($p64), true);
    $signature = google_b64url_decode($s64);
    if (!is_array($header) || !is_array($claims) || $signature === false) {
        return $fail('malformed');
    }
    // Only RS256 is accepted ("none", HS256 and other algorithm tricks are refused)
    if (($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null)) {
        return $fail('algorithm');
    }

    $find_key = function (array $keys) use ($header): ?string {
        foreach ($keys as $jwk) {
            if (is_array($jwk) && ($jwk['kid'] ?? null) === $header['kid'] && ($jwk['alg'] ?? 'RS256') === 'RS256'
                && ($jwk['use'] ?? 'sig') === 'sig') {
                return google_jwk_to_pem($jwk);
            }
        }
        return null;
    };
    $pem = $find_key($jwks ?? google_jwks());
    if ($pem === null && $jwks === null) {
        $pem = $find_key(google_jwks(true)); // Google rotated its keys: refresh once
    }
    if ($pem === null) {
        return $fail('unknown signing key');
    }
    if (openssl_verify($h64 . '.' . $p64, $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) {
        return $fail('bad signature');
    }

    $client_id = (string) app_config('GOOGLE_CLIENT_ID');
    $aud = $claims['aud'] ?? null;
    $aud_ok = is_string($aud) ? hash_equals($client_id, $aud)
        : (is_array($aud) && in_array($client_id, $aud, true) && ($claims['azp'] ?? null) === $client_id);

    if (!in_array($claims['iss'] ?? null, GOOGLE_ISSUERS, true)) {
        return $fail('issuer');
    }
    if ($client_id === '' || !$aud_ok) {
        return $fail('audience');
    }
    if (!is_int($claims['exp'] ?? null) || $claims['exp'] < $now - GOOGLE_CLOCK_SKEW) {
        return $fail('expired');
    }
    if (!is_int($claims['iat'] ?? null) || $claims['iat'] > $now + GOOGLE_CLOCK_SKEW) {
        return $fail('issued in the future');
    }
    if ($nonce === '' || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
        return $fail('nonce');
    }
    if (($claims['email_verified'] ?? false) !== true && ($claims['email_verified'] ?? '') !== 'true') {
        return $fail('email not verified');
    }
    if (!is_string($claims['sub'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $claims['sub'])) {
        return $fail('subject');
    }
    $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        return $fail('email');
    }
    $claims['email'] = $email;
    return $claims;
}

/** A free username suggestion based on the Google email (e.g. "asha.m" or "asha.m27"). */
function google_suggest_username(PDO $pdo, string $email, string $name = ''): string
{
    $base = preg_replace('/[^A-Za-z0-9_.-]/', '', strstr($email, '@', true) ?: '');
    if (strlen($base) < 3) {
        $base = preg_replace('/[^A-Za-z0-9_.-]/', '', str_replace(' ', '.', $name));
    }
    $base = substr(trim($base, '.-'), 0, 40);
    if (strlen($base) < 3) {
        $base = 'student';
    }
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
    for ($i = 0; $i < 25; $i++) {
        $candidate = $i === 0 ? $base : $base . random_int(10, $i < 10 ? 99 : 9999);
        $stmt->execute([':u' => $candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
    }
    return $base . bin2hex(random_bytes(3));
}

/**
 * Act on a verified Google identity. Returns where to send the browser next
 * (relative to /account/ or a safe local path) and leaves a flash message where useful.
 */
function google_handle_identity(PDO $pdo, array $claims, array $flow): string
{
    $sub = (string) $claims['sub'];
    $return = (string) ($flow['return'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username, status FROM users WHERE google_sub = :s LIMIT 1');
    $stmt->execute([':s' => $sub]);
    $linked = $stmt->fetch() ?: null;

    // Connect Google to the account that is signed in
    if (($flow['mode'] ?? '') === 'link') {
        $user = current_user();
        if (!$user || (int) $user['id'] !== (int) ($flow['user_id'] ?? -1)) {
            flash('error', 'Please sign in again, then connect your Google account.');
            return 'login.php';
        }
        if ($linked && (int) $linked['id'] !== (int) $user['id']) {
            flash('error', 'That Google account is already connected to a different account on this site.');
            return 'security.php';
        }
        if ($linked) {
            flash('success', 'Your Google account is already connected.');
            return 'security.php';
        }
        $upd = $pdo->prepare('UPDATE users SET google_sub = :s WHERE id = :id AND google_sub IS NULL');
        $upd->execute([':s' => $sub, ':id' => (int) $user['id']]);
        if ($upd->rowCount() !== 1) {
            flash('error', 'Your account is already connected to another Google account. Disconnect it first.');
            return 'security.php';
        }
        flash('success', 'Google is connected. You can now sign in with Google.');
        return 'security.php';
    }

    // Returning user
    if ($linked) {
        if ($linked['status'] !== 'active') {
            flash('error', 'This account has been suspended. Please contact the administrator.');
            return 'login.php';
        }
        clear_failed_logins($pdo, 'user', client_ip());
        log_in_user($pdo, $linked);
        return $return !== '' ? $return : 'dashboard.php';
    }

    // Unknown Google account whose email already belongs to an account: do NOT link automatically
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $claims['email']]);
    if ($stmt->fetchColumn()) {
        flash('error', 'An account with this email address already exists. Sign in with your username and password, '
            . 'then connect Google under Password & Security.');
        return 'login.php';
    }

    // New student: confirm a few details and accept the Terms before the account is created
    $_SESSION['google_pending'] = [
        'sub' => $sub,
        'email' => $claims['email'],
        'name' => mb_substr(trim((string) ($claims['name'] ?? '')), 0, 120),
        'return' => $return,
        'expires' => time() + GOOGLE_PENDING_TTL,
    ];
    return 'google_finish.php';
}
