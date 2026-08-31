<?php
declare(strict_types=1);

namespace Techbiss\Core;

use Techbiss\Repo\AdminRepo;

final class Auth
{
    private static ?array $user = null;
    /** @var array<int,string>|null */
    private static ?array $permissions = null;
    private static AdminRepo $repo;
    private static array $config = [];

    public static function boot(array $securityConfig): void
    {
        self::$repo   = new AdminRepo();
        self::$config = $securityConfig;
    }

    /** @return array{ok:bool,message:string,user:?array} */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email    = mb_strtolower(trim($email));
        $maxTries = (int) (self::$config['login_max_attempts'] ?? 6);
        $lockout  = (int) (self::$config['login_lockout'] ?? 900);

        if (self::$repo->failedAttempts($email, $ip, $lockout) >= $maxTries) {
            return [
                'ok'      => false,
                'message' => 'Too many failed attempts. Please wait ' . (int) ceil($lockout / 60) . ' minutes and try again.',
                'user'    => null,
            ];
        }

        $user = self::$repo->findByEmail($email);
        // Always hash something so the response time does not reveal whether
        // the account exists.
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $ok   = password_verify($password, (string) $hash);

        if (!$ok || $user === null) {
            self::$repo->recordAttempt($email, $ip, false);
            return ['ok' => false, 'message' => 'Those credentials do not match our records.', 'user' => null];
        }
        if ((int) $user['is_active'] !== 1) {
            self::$repo->recordAttempt($email, $ip, false);
            return ['ok' => false, 'message' => 'This account has been deactivated.', 'user' => null];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            self::$repo->update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        self::$repo->recordAttempt($email, $ip, true);
        self::$repo->clearAttempts($email, $ip);
        self::login($user, $ip);

        return ['ok' => true, 'message' => '', 'user' => $user];
    }

    public static function login(array $user, string $ip = ''): void
    {
        Session::regenerate();
        Session::set('admin_id', (int) $user['id']);
        Session::set('admin_fingerprint', self::fingerprint());
        self::$user        = null;
        self::$permissions = null;
        self::$repo->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);
    }

    public static function logout(): void
    {
        Session::forget('admin_id');
        Session::forget('admin_fingerprint');
        self::$user        = null;
        self::$permissions = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('admin_id');
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }
        // Bind the session to a coarse client fingerprint so a stolen cookie is
        // not enough on its own.
        if (Session::get('admin_fingerprint') !== self::fingerprint()) {
            Session::forget('admin_id');
            return null;
        }
        $user = self::$repo->find((int) $id);
        if ($user === null || (int) $user['is_active'] !== 1) {
            Session::forget('admin_id');
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): int
    {
        $u = self::user();
        return $u === null ? 0 : (int) $u['id'];
    }

    public static function name(): string
    {
        $u = self::user();
        return $u === null ? '' : (string) $u['name'];
    }

    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (string) $u['role_slug'] === 'super-admin';
    }

    /** @return array<int,string> */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $u = self::user();
        if ($u === null) {
            return self::$permissions = [];
        }
        return self::$permissions = self::$repo->permissionsForRole((int) $u['role_id']);
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    /** True when the user holds any one of the listed permissions. */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private static function fingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', (string) $ua . '|' . (string) (self::$config['app_key'] ?? ''));
    }
}
