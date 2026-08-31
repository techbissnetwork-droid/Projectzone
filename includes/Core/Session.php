<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Session
{
    private static bool $started = false;

    public static function start(array $security): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;
            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_name($security['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) $security['cookie_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (int) $security['session_lifetime']);
        session_start();
        self::$started = true;

        $ttl = (int) $security['session_lifetime'];
        $now = time();
        if (isset($_SESSION['__last_seen']) && ($now - (int) $_SESSION['__last_seen']) > $ttl) {
            self::destroy();
            session_start();
        }
        $_SESSION['__last_seen'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
            }
            session_destroy();
        }
    }

    // ------------------------------------------------------------------
    // Flash messages
    // ------------------------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['__flash'] ?? [];
        unset($_SESSION['__flash']);
        return $flash;
    }

    /** Remember submitted form input so a failed validation can repopulate it. */
    public static function flashInput(array $input): void
    {
        unset($input['csrf_token'], $input['password'], $input['password_confirm']);
        $_SESSION['__old'] = $input;
    }

    public static function takeOld(): array
    {
        $old = $_SESSION['__old'] ?? [];
        unset($_SESSION['__old']);
        return $old;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['__errors'] = $errors;
    }

    public static function takeErrors(): array
    {
        $errors = $_SESSION['__errors'] ?? [];
        unset($_SESSION['__errors']);
        return $errors;
    }
}
