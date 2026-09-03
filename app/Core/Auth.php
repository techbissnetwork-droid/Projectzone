<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-backed authentication for the three TECHBISS portals.
 *
 * A single `users` table carries a `role` column; each portal accepts only the
 * roles that belong to it, so a client credential can never open the admin
 * console even if it is valid. Throttling is recorded per email + IP pair.
 */
final class Auth
{
    public const PORTALS = [
        'admin' => ['roles' => ['owner', 'admin'], 'label' => 'Admin Console', 'home' => '/admin'],
        'staff' => ['roles' => ['manager', 'engineer', 'support'], 'label' => 'Staff Workspace', 'home' => '/staff'],
        'client' => ['roles' => ['client'], 'label' => 'Client Portal', 'home' => '/client'],
    ];

    private const MAX_ATTEMPTS = 6;
    private const DECAY_SECONDS = 900;

    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(private Database $db, private Session $session)
    {
    }

    public static function portalForRole(string $role): ?string
    {
        foreach (self::PORTALS as $portal => $meta) {
            if (in_array($role, $meta['roles'], true)) {
                return $portal;
            }
        }
        return null;
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $id = $this->session->get('auth_user_id');
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return $this->user = null;
        }

        $user = $this->db->first(
            'SELECT id, uuid, name, email, role, status, company, avatar_color, last_login_at, created_at
             FROM users WHERE id = ? LIMIT 1',
            [(int) $id]
        );

        if ($user === null || $user['status'] !== 'active') {
            $this->session->forget('auth_user_id');
            return $this->user = null;
        }

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function portal(): ?string
    {
        $role = $this->role();
        return $role === null ? null : self::portalForRole($role);
    }

    public function canAccessPortal(string $portal): bool
    {
        $role = $this->role();
        return $role !== null && in_array($role, self::PORTALS[$portal]['roles'] ?? [], true);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role(), ['owner', 'admin'], true);
    }

    /**
     * @return array{0:bool,1:string} [success, message]
     */
    public function attempt(string $portal, string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        $allowedRoles = self::PORTALS[$portal]['roles'] ?? [];
        if ($allowedRoles === []) {
            return [false, 'Unknown sign-in portal.'];
        }

        if ($this->tooManyAttempts($email, $ip)) {
            return [false, 'Too many sign-in attempts. Please try again in 15 minutes.'];
        }

        $user = $this->db->first(
            'SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';
        $passwordOk = password_verify($password, $hash);

        if ($user === null || !$passwordOk) {
            $this->recordAttempt($email, $ip, false);
            return [false, 'Those credentials do not match our records.'];
        }

        if ($user['status'] !== 'active') {
            $this->recordAttempt($email, $ip, false);
            return [false, 'This account is suspended. Contact your account manager.'];
        }

        if (!in_array($user['role'], $allowedRoles, true)) {
            $this->recordAttempt($email, $ip, false);
            $correct = self::portalForRole($user['role']);
            return [false, $correct
                ? 'This account signs in through the ' . self::PORTALS[$correct]['label'] . '.'
                : 'This account cannot access that portal.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int) $user['id']]);
        }

        $this->session->regenerate();
        $this->session->put('auth_user_id', (int) $user['id']);
        $this->session->put('auth_portal', $portal);
        $this->resolved = false;

        $this->db->update('users', ['last_login_at' => gmdate('c')], 'id = :id', ['id' => (int) $user['id']]);
        $this->recordAttempt($email, $ip, true);
        $this->log((int) $user['id'], 'auth.login', $portal . ' portal sign-in', $ip);

        return [true, 'Welcome back, ' . explode(' ', (string) $user['name'])[0] . '.'];
    }

    public function logout(string $ip = '0.0.0.0'): void
    {
        if ($id = $this->id()) {
            $this->log($id, 'auth.logout', 'Signed out', $ip);
        }
        $this->session->forget('auth_user_id');
        $this->session->forget('auth_portal');
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = true;
    }

    private function tooManyAttempts(string $email, string $ip): bool
    {
        if (!$this->db->tableExists('login_attempts')) {
            return false;
        }
        $since = gmdate('c', time() - self::DECAY_SECONDS);
        $count = (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND created_at > ? AND (email = ? OR ip_address = ?)',
            [$since, $email, $ip],
            0
        );
        return $count >= self::MAX_ATTEMPTS;
    }

    private function recordAttempt(string $email, string $ip, bool $successful): void
    {
        if (!$this->db->tableExists('login_attempts')) {
            return;
        }
        $this->db->insert('login_attempts', [
            'email' => substr($email, 0, 190),
            'ip_address' => $ip,
            'successful' => $successful ? 1 : 0,
            'created_at' => gmdate('c'),
        ]);
        if ($successful) {
            $this->db->statement('DELETE FROM login_attempts WHERE successful = 0 AND (email = ? OR ip_address = ?)', [$email, $ip]);
        }
    }

    public function log(int $userId, string $action, string $description, string $ip = '0.0.0.0'): void
    {
        if (!$this->db->tableExists('activity_log')) {
            return;
        }
        $this->db->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ip,
            'created_at' => gmdate('c'),
        ]);
    }
}
