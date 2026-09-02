<?php
/** Admin authentication: login, session, and brute-force throttling. */

function auth_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }
    $user = one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    return $user;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

/** Call at the top of every admin page except the login screen. */
function auth_require(): void
{
    if (!auth_check()) {
        redirect('admin/login.php');
    }
}

function auth_attempt(string $email, string $password): bool
{
    $user = one('SELECT * FROM users WHERE email = :e', ['e' => mb_strtolower($email)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throttle_hit('login');
        return false;
    }

    // Re-hash if PHP's default cost has moved on since the password was set.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', (int) $user['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    db_update('users', (int) $user['id'], ['last_login_at' => now()]);
    throttle_clear('login');
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------ throttle --- */

function throttle_hit(string $bucket): void
{
    db_insert('throttle', ['bucket' => $bucket, 'ip' => client_ip(), 'created_at' => now()]);
}

function throttle_count(string $bucket, int $withinSeconds): int
{
    $since = date('Y-m-d H:i:s', time() - $withinSeconds);
    return (int) scalar(
        'SELECT COUNT(*) FROM throttle WHERE bucket = :b AND ip = :i AND created_at > :s',
        ['b' => $bucket, 'i' => client_ip(), 's' => $since]
    );
}

function throttle_clear(string $bucket): void
{
    q('DELETE FROM throttle WHERE bucket = :b AND ip = :i', ['b' => $bucket, 'i' => client_ip()]);
}

/** Drop rows older than a day so the table cannot grow without bound. */
function throttle_prune(): void
{
    q('DELETE FROM throttle WHERE created_at < :s', ['s' => date('Y-m-d H:i:s', time() - 86400)]);
}
