<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

final class AdminRepo
{
    private function db(): Database
    {
        return Database::instance();
    }

    public function find(int $id): ?array
    {
        return $this->db()->first(
            'SELECT a.*, r.name AS role_name, r.slug AS role_slug FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ?',
            [$id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db()->first(
            'SELECT a.*, r.name AS role_name, r.slug AS role_slug FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.email = ?',
            [$email]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db()->all(
            'SELECT a.*, r.name AS role_name, r.slug AS role_slug FROM admins a JOIN roles r ON r.id = a.role_id ORDER BY a.name'
        );
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->insert('admins', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('admins', $data, 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db()->delete('admins', 'id', $id);
    }

    public function emailTaken(string $email, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM admins WHERE email = ?';
        $params = [$email];
        if ($ignoreId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return $this->db()->int($sql, $params) > 0;
    }

    public function countSuperAdmins(?int $excludingId = null): int
    {
        $sql    = "SELECT COUNT(*) FROM admins a JOIN roles r ON r.id = a.role_id WHERE r.slug = 'super-admin' AND a.is_active = 1";
        $params = [];
        if ($excludingId !== null) {
            $sql     .= ' AND a.id <> ?';
            $params[] = $excludingId;
        }
        return $this->db()->int($sql, $params);
    }

    // -----------------------------------------------------------------
    // Roles & permissions
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function roles(): array
    {
        return $this->db()->all(
            'SELECT r.*, (SELECT COUNT(*) FROM admins a WHERE a.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
             FROM roles r ORDER BY r.id'
        );
    }

    public function role(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    public function roleBySlug(string $slug): ?array
    {
        return $this->db()->first('SELECT * FROM roles WHERE slug = ?', [$slug]);
    }

    public function createRole(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->insert('roles', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    public function updateRole(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('roles', $data, 'id', $id);
    }

    public function deleteRole(int $id): void
    {
        $this->db()->delete('roles', 'id', $id);
    }

    /** @return array<int,array<string,mixed>> */
    public function permissions(): array
    {
        return $this->db()->all('SELECT * FROM permissions ORDER BY sort_order ASC, id ASC');
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function permissionsGrouped(): array
    {
        $out = [];
        foreach ($this->permissions() as $p) {
            $out[(string) $p['group_name']][] = $p;
        }
        return $out;
    }

    /** @return array<int,string> permission slugs held by a role */
    public function permissionsForRole(int $roleId): array
    {
        return array_map('strval', $this->db()->column(
            'SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?',
            [$roleId]
        ));
    }

    /** @param array<int,string> $slugs */
    public function syncRolePermissions(int $roleId, array $slugs): void
    {
        $this->db()->transaction(function (Database $db) use ($roleId, $slugs): void {
            $db->run('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
            if ($slugs === []) {
                return;
            }
            $place = implode(',', array_fill(0, count($slugs), '?'));
            $ids   = $db->column("SELECT id FROM permissions WHERE slug IN ($place)", array_values($slugs));
            foreach ($ids as $pid) {
                $db->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => (int) $pid]);
            }
        });
    }

    // -----------------------------------------------------------------
    // Login attempts
    // -----------------------------------------------------------------

    public function recordAttempt(string $identifier, string $ip, bool $success): void
    {
        $this->db()->insert('login_attempts', [
            'identifier' => mb_substr($identifier, 0, 190),
            'ip_address' => $ip,
            'successful' => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function failedAttempts(string $identifier, string $ip, int $withinSeconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $withinSeconds);
        return $this->db()->int(
            'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND created_at > ? AND (identifier = ? OR ip_address = ?)',
            [$since, $identifier, $ip]
        );
    }

    public function clearAttempts(string $identifier, string $ip): void
    {
        $this->db()->run('DELETE FROM login_attempts WHERE identifier = ? OR ip_address = ?', [$identifier, $ip]);
    }

    public function pruneAttempts(int $olderThanSeconds = 86400): void
    {
        $this->db()->run('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $olderThanSeconds)]);
    }
}
