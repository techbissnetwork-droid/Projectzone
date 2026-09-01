<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Admin session authentication + CSRF protection.
 */
class Auth
{
    /**
     * A fixed, valid bcrypt hash of a password nobody has typed - see
     * MemberAuth::DUMMY_HASH for why attempt() below verifies against this
     * when no matching username exists, instead of skipping the check.
     */
    private const DUMMY_HASH = '$2y$12$ydhJOyyxC63udEOptGMSW.1/HRkdB1FnB4ttt8.7I25SQI5h30.Ru';

    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($config['admin']['session_name']);
            session_set_cookie_params(Request::sessionCookieParams());
            session_start();
        }
    }

    /**
     * Same rule as the member session: an admin action that calls out to a
     * gateway can take fifteen seconds, and holding the session lock for it
     * freezes every other admin tab - the panel looks hung while it is only
     * waiting for itself. Pair with resumeSession() when a flash message or
     * anything else still has to be written.
     */
    public static function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function resumeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $stmt->closeCursor();
        // Always verify against something - a real hash when the username
        // exists, the fixed dummy one when it doesn't - so a probe against a
        // nonexistent admin username costs the same as a wrong-password
        // attempt against a real one, instead of returning near-instantly.
        $verified = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        if ($user && $verified) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];
            $_SESSION['admin_user'] = $user['username'];
            $_SESSION['admin_pw'] = (int)($user['pw_changed'] ?? 0);
            $_SESSION['admin_seen'] = time();
            return true;
        }
        return false;
    }

    public static function check(): bool
    {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        // IDLE TIMEOUT.
        //
        // The session cookie already ends with the browser, which covers the
        // laptop that gets closed. It does not cover the one left open on a
        // desk, and this panel can move money, read every member's address and
        // download the database - so an admin session that has done nothing
        // for half a day should not still be a live one.
        //
        // Counted from the last request rather than from login, so an operator
        // working all day is never interrupted; it is inactivity that ends it.
        // Zero switches it off for anyone whose workflow it gets in the way of.
        $idle = max(0, min(43200, (int)Database::setting('admin_idle_minutes', '720')));
        $seen = (int)($_SESSION['admin_seen'] ?? 0);
        if ($idle > 0 && $seen > 0 && time() - $seen > $idle * 60) {
            self::logout();
            return false;
        }
        // Written at most once a minute: every admin page load would otherwise
        // rewrite the session file for a value that has not meaningfully
        // changed, and the session lock is the thing that makes the panel feel
        // slow.
        if (time() - $seen > 60) {
            $_SESSION['admin_seen'] = time();
        }
        // Changing the admin password ends every other admin session.
        //
        // This account is seeded with a generated password written to a file
        // on disk, so "change it, that closes the door" has to be true - and
        // it is not true while a session opened with the old password keeps
        // working. One indexed lookup on a table with a handful of rows.
        //
        // A session with no stamp predates this and adopts the current value
        // instead of being thrown out, so shipping it logs nobody out.
        try {
            $stmt = Database::pdo()->prepare('SELECT pw_changed FROM users WHERE id = ?');
            $stmt->execute([(int)$_SESSION['admin_id']]);
            $gen = $stmt->fetchColumn();
            if ($gen === false) {
                self::logout();          // the account was deleted
                return false;
            }
            if (!isset($_SESSION['admin_pw'])) {
                $_SESSION['admin_pw'] = (int)$gen;
            } elseif ((int)$_SESSION['admin_pw'] !== (int)$gen) {
                self::logout();
                return false;
            }
        } catch (\Throwable $e) {
            // A database that cannot answer is not a reason to lock the
            // operator out of the panel they would use to fix it.
        }
        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf'] ?? '';
        if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid CSRF token. Go back and retry.');
        }
    }
}
