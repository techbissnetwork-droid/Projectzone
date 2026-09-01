<?php
/** Session + authentication + CSRF helpers for the admin area. */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path'     => '/',
    ]);
    session_start();
}

// ── Brute-force protection (per-IP login throttle) ────────────────
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr((string) $ip, 0, 64);
}

function login_locked(?string $ip = null): bool
{
    $ip = $ip ?? client_ip();
    $st = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?");
    $st->execute([$ip, time() - LOGIN_WINDOW]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FAILS;
}

function record_login_fail(?string $ip = null): void
{
    $ip = $ip ?? client_ip();
    db()->prepare("INSERT INTO login_attempts (ip, at) VALUES (?, ?)")->execute([$ip, time()]);
    // opportunistic cleanup of old rows
    db()->prepare("DELETE FROM login_attempts WHERE at < ?")->execute([time() - LOGIN_WINDOW * 4]);
}

function clear_login_fails(?string $ip = null): void
{
    db()->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip ?? client_ip()]);
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $u = null;
    if ($u === null) {
        $st = db()->prepare("SELECT id, username FROM users WHERE id = ?");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . url('/admin/login.php'));
        exit;
    }
}

function attempt_login(string $username, string $password): bool
{
    $st = db()->prepare("SELECT * FROM users WHERE username = ?");
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        return true;
    }
    return false;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

/** True while the default password is still in use (security nudge). */
function using_default_password(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    $st = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
    $st->execute([$u['id']]);
    $hash = (string) $st->fetchColumn();
    return $u['username'] === 'admin' && password_verify('admin', $hash);
}

// ── CSRF ──────────────────────────────────────────────────────────
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    start_session();
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Invalid or expired session token. Please go back and try again.');
    }
}

/** Flash messages. */
function flash(string $msg = null): ?string
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
