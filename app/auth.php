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

/** A readable temporary password for a new client account. */
function temp_password(): string
{
    $words = ['harbour', 'signal', 'lantern', 'copper', 'meridian', 'anchor', 'quartz', 'beacon'];
    return $words[random_int(0, count($words) - 1)] . '-' . random_int(1000, 9999);
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
