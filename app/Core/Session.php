<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private bool $started = false;

    public function __construct(private array $config = [])
    {
    }

    public function start(): void
    {
        if ($this->started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        session_set_cookie_params([
            'lifetime' => (int) ($this->config['lifetime'] ?? 0),
            'path' => $this->config['path'] ?? '/',
            'domain' => $this->config['domain'] ?? '',
            'secure' => (bool) ($this->config['secure'] ?? false),
            'httponly' => true,
            'samesite' => $this->config['same_site'] ?? 'Lax',
        ]);
        session_name((string) ($this->config['name'] ?? 'techbiss_session'));
        session_start();
        $this->started = true;

        // Rotate the id periodically to blunt fixation attacks.
        $now = time();
        $rotated = (int) ($_SESSION['__rotated_at'] ?? 0);
        if ($rotated === 0) {
            $_SESSION['__rotated_at'] = $now;
        } elseif ($now - $rotated > 1800) {
            session_regenerate_id(true);
            $_SESSION['__rotated_at'] = $now;
        }
    }

    public function regenerate(): void
    {
        if ($this->started && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['__rotated_at'] = time();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['__flash'][$key] = $value;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['__flash'][$key] ?? $default;
        unset($_SESSION['__flash'][$key]);
        return $value;
    }

    public function flashAll(): array
    {
        $all = $_SESSION['__flash'] ?? [];
        unset($_SESSION['__flash']);
        return is_array($all) ? $all : [];
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->started = false;
    }
}
