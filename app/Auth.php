<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS = 6;
    private const WINDOW_MIN   = 15;

    private static bool $resolved = false;
    private static ?array $current = null;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$current;
        }
        self::$resolved = true;
        $id = (int)($_SESSION['uid'] ?? 0);
        if ($id <= 0) {
            return self::$current = null;
        }
        $row = Database::one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        if (!$row || $row['status'] !== 'active') {
            self::logout();
            return self::$current = null;
        }
        return self::$current = $row;
    }

    /** Drop the cached identity so the next read reflects a sign-in or sign-out. */
    public static function forget(): void
    {
        self::$resolved = false;
        self::$current = null;
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function throttled(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
        $n = (int)Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = :e AND created_at > :s',
            ['e' => mb_strtolower($email), 's' => $since], 0
        );
        return $n >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $email): void
    {
        Database::insert('login_attempts', [
            'identifier' => mb_strtolower($email),
            'ip'         => client_ip(),
            'created_at' => now(),
        ]);
    }

    public static function clearFailures(string $email): void
    {
        Database::run('DELETE FROM login_attempts WHERE identifier = :e', ['e' => mb_strtolower($email)]);
    }

    /** @return array{0:bool,1:string} [success, message] */
    public static function attempt(string $email, string $password): array
    {
        $email = trim(mb_strtolower($email));
        if ($email === '' || $password === '') {
            return [false, 'Enter your email and password.'];
        }
        if (self::throttled($email)) {
            return [false, 'Too many failed attempts. Try again in 15 minutes.'];
        }

        $u = Database::one('SELECT * FROM users WHERE email = :e', ['e' => $email]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            self::recordFailure($email);
            return [false, 'That email and password do not match.'];
        }
        if ($u['status'] !== 'active') {
            return [false, 'This account is suspended. Contact support.'];
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], (int)$u['id']);
        }

        self::clearFailures($email);
        self::login($u);
        return [true, ''];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        self::forget();
        Database::update('users', ['last_login_at' => now()], (int)$user['id']);
        log_activity('login', 'user', (int)$user['id']);
    }

    public static function logout(): void
    {
        self::forget();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Random password for accounts the admin creates on a client's behalf. */
    public static function randomPassword(int $len = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
