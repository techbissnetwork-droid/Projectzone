<?php
/**
 * Sign-in for both sides of the app.
 *
 * Admins and staff use /admin. Clients use /client. Same users table, told
 * apart by the role column, so one person can never end up with two accounts
 * for the same email address.
 */

const ROLE_ADMIN  = 'admin';
const ROLE_STAFF  = 'staff';
const ROLE_CLIENT = 'client';

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $user = null;
    }
    $row = db_one('SELECT * FROM users WHERE id = ? AND status = ?', [$id, 'active']);
    return $user = $row ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_staff(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], [ROLE_ADMIN, ROLE_STAFF], true);
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === ROLE_ADMIN;
}

function is_client(): bool
{
    $u = current_user();
    return $u && $u['role'] === ROLE_CLIENT;
}

/** Gate the admin area. */
function require_staff(): void
{
    if (!is_staff()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
}

/** Only a full admin may do this. */
function require_admin(): void
{
    require_staff();
    if (!is_admin()) {
        http_response_code(403);
        exit('That area is for administrators only.');
    }
}

/** Gate the client portal. */
function require_client(): void
{
    if (!is_client()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
}

/* --- throttling -------------------------------------------------------- */

function login_too_many(string $identifier): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    return db_count(
        'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?',
        [$identifier, $since]
    ) >= 8;
}

function login_record_failure(string $identifier): void
{
    db_insert('login_attempts', ['identifier' => $identifier, 'attempted_at' => now()]);
    db_run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

function login_clear(string $identifier): void
{
    db_run('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
}

/**
 * Try to sign someone in.
 * Returns the user on success, or null. $error is filled in on failure.
 */
function attempt_login(string $email, string $password, array $allowedRoles, ?string &$error = null): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        $error = 'Enter your email address and password.';
        return null;
    }
    if (login_too_many($email)) {
        $error = 'Too many attempts. Wait fifteen minutes and try again.';
        return null;
    }

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    $ok   = $user && $user['password_hash'] !== '' && password_verify($password, $user['password_hash']);

    if (!$ok) {
        login_record_failure($email);
        $error = 'That email and password do not match.';
        return null;
    }
    if ($user['status'] !== 'active') {
        $error = 'That account has been suspended. Email support@techbiss.com.';
        return null;
    }
    if (!in_array($user['role'], $allowedRoles, true)) {
        $error = $user['role'] === ROLE_CLIENT
            ? 'That is a client account. Sign in through the client portal instead.'
            : 'That account cannot sign in here.';
        return null;
    }

    login_clear($email);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    db_update('users', (int) $user['id'], ['last_login_at' => now()]);
    log_activity('Signed in', 'user', (int) $user['id'], $user['name']);

    return $user;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Passwords must be worth having. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Use at least 10 characters.';
    }
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Use at least one letter and one number.';
    }
    return null;
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function log_activity(string $action, string $entity = '', int $entityId = 0, ?string $actor = null): void
{
    try {
        $u = current_user();
        db_insert('activity_log', [
            'user_id'    => $u['id'] ?? null,
            'actor'      => $actor ?? ($u['name'] ?? 'System'),
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        // Never let the audit trail break a real action.
    }
}

/* ---------------------------------------------------------------------- */
/* one-time sign-in codes                                                 */
/* ---------------------------------------------------------------------- */
/*
 * Clients do not have passwords. They type their email address, we email a
 * six-digit code, and they type that back. Only staff sign in with a password,
 * so a broken mail server can never lock you out of your own admin area.
 */

const LOGIN_CODE_MINUTES  = 10;   /* how long a code stays valid */
const LOGIN_CODE_ATTEMPTS = 5;    /* wrong guesses before the code dies */

/**
 * Issue a code for a client account and return it, so the caller can email it.
 * Returns null when there is no such client — the caller must still behave
 * exactly the same either way, so the page never reveals who has an account.
 */
function login_code_request(string $email): ?string
{
    $email = strtolower(trim($email));
    if (!valid_email($email)) {
        return null;
    }

    /* Same throttle as a password login, so nobody can spam an inbox. */
    if (login_too_many('code:' . $email)) {
        return null;
    }
    login_record_failure('code:' . $email);

    $user = db_one('SELECT * FROM users WHERE email = ? AND status = ?', [$email, 'active']);
    if (!$user || $user['role'] !== ROLE_CLIENT) {
        return null;
    }

    /* Asking for a new code cancels any earlier one. */
    db_run('DELETE FROM login_codes WHERE user_id = ?', [$user['id']]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_insert('login_codes', [
        'user_id'    => (int) $user['id'],
        'code_hash'  => hash('sha256', $code),
        'expires_at' => date('Y-m-d H:i:s', time() + LOGIN_CODE_MINUTES * 60),
        'attempts'   => 0,
        'created_at' => now(),
    ]);

    return $code;
}

/**
 * Check a code and sign the client in.
 * Returns the user, or null with $error explaining what to tell them.
 */
function login_code_verify(string $email, string $code, ?string &$error = null): ?array
{
    $email = strtolower(trim($email));
    $code  = preg_replace('/\D/', '', $code);

    if ($code === '' || strlen($code) !== 6) {
        $error = 'Enter the six-digit code from the email.';
        return null;
    }

    $user = db_one('SELECT * FROM users WHERE email = ? AND status = ?', [$email, 'active']);
    if (!$user || $user['role'] !== ROLE_CLIENT) {
        $error = 'That code did not work. Ask for a new one.';
        return null;
    }

    $row = db_one(
        'SELECT * FROM login_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC',
        [$user['id']]
    );
    if (!$row) {
        $error = 'That code has already been used. Ask for a new one.';
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        db_run('DELETE FROM login_codes WHERE id = ?', [$row['id']]);
        $error = 'That code has expired — they last ' . LOGIN_CODE_MINUTES . ' minutes. Ask for a new one.';
        return null;
    }
    if ((int) $row['attempts'] >= LOGIN_CODE_ATTEMPTS) {
        db_run('DELETE FROM login_codes WHERE id = ?', [$row['id']]);
        $error = 'Too many wrong tries. Ask for a new code.';
        return null;
    }

    if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
        db_run('UPDATE login_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        $left  = LOGIN_CODE_ATTEMPTS - ((int) $row['attempts'] + 1);
        $error = $left > 0
            ? 'That code is not right. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.'
            : 'Too many wrong tries. Ask for a new code.';
        return null;
    }

    /* Correct. Burn the code and sign them in. */
    db_run('UPDATE login_codes SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    login_clear('code:' . $email);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    db_update('users', (int) $user['id'], ['last_login_at' => now()]);
    log_activity('Signed in with a code', 'user', (int) $user['id'], $user['name']);

    return $user;
}

/** Clear out codes that are spent or long expired. */
function login_code_prune(): void
{
    db_run('DELETE FROM login_codes WHERE expires_at < ? OR used_at IS NOT NULL',
        [date('Y-m-d H:i:s', time() - 3600)]);
}
