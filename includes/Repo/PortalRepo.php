<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

/**
 * The client portal: emailed one-time codes to sign in, and the requests a
 * client raises against a project already on file. Kept separate from
 * CustomerRepo/ClientProjectRepo since neither of those is about the portal
 * itself, and separate from LeadRepo/AdminRepo since this is a different
 * audience (a client, not a visitor or an administrator).
 */
final class PortalRepo
{
    private function db(): Database
    {
        return Database::instance();
    }

    // -----------------------------------------------------------------
    // Sign-in
    // -----------------------------------------------------------------

    /** Only a customer with at least one project on file may use the portal. */
    public function eligibleCustomer(string $email): ?array
    {
        $customer = $this->db()->first('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($customer === null) {
            return null;
        }
        $hasProject = $this->db()->int('SELECT COUNT(*) FROM client_projects WHERE customer_id = ?', [(int) $customer['id']]) > 0;
        return $hasProject ? $customer : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function projectsForCustomer(int $customerId): array
    {
        return $this->db()->all('SELECT * FROM client_projects WHERE customer_id = ? ORDER BY created_at DESC', [$customerId]);
    }

    // -----------------------------------------------------------------
    // One-time codes
    // -----------------------------------------------------------------

    /** How many codes have gone out for this email or from this IP recently — the send-throttle. */
    public function recentOtpCount(string $email, string $ip, int $withinSeconds = 600): int
    {
        $since = date('Y-m-d H:i:s', time() - $withinSeconds);
        return $this->db()->int(
            'SELECT COUNT(*) FROM customer_otps WHERE created_at > ? AND (email = ? OR ip_address = ?)',
            [$since, $email, $ip]
        );
    }

    public function createOtp(string $email, string $codeHash, int $ttlSeconds, string $ip): int
    {
        return $this->db()->insert('customer_otps', [
            'email'      => $email,
            'code_hash'  => $codeHash,
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** The one code still open for this email, if any. */
    public function latestOtp(string $email): ?array
    {
        return $this->db()->first(
            'SELECT * FROM customer_otps WHERE email = ? AND consumed_at IS NULL ORDER BY created_at DESC LIMIT 1',
            [$email]
        );
    }

    public function recordOtpAttempt(int $id): void
    {
        $this->db()->run('UPDATE customer_otps SET attempts = attempts + 1 WHERE id = ?', [$id]);
    }

    public function consumeOtp(int $id): void
    {
        $this->db()->run('UPDATE customer_otps SET consumed_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
    }

    public function pruneOtps(int $olderThanSeconds = 86400): void
    {
        $this->db()->run('DELETE FROM customer_otps WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $olderThanSeconds)]);
    }

    // -----------------------------------------------------------------
    // Client requests (upgrade / update / maintenance / support)
    // -----------------------------------------------------------------

    /** Per-customer submission throttle, same idea as LeadRepo::recentSubmissionCount. */
    public function recentRequestCount(int $customerId, int $seconds = 300): int
    {
        $since = date('Y-m-d H:i:s', time() - $seconds);
        return $this->db()->int('SELECT COUNT(*) FROM client_requests WHERE customer_id = ? AND created_at > ?', [$customerId, $since]);
    }

    public function createRequest(array $data): int
    {
        $now                = date('Y-m-d H:i:s');
        $data['reference']  = $this->nextReference();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        return $this->db()->insert('client_requests', $data);
    }

    /** @return array<int,array<string,mixed>> */
    public function requestsForCustomer(int $customerId): array
    {
        return $this->db()->all(
            'SELECT r.*, p.name AS project_name
             FROM client_requests r LEFT JOIN client_projects p ON p.id = r.client_project_id
             WHERE r.customer_id = ? ORDER BY r.created_at DESC',
            [$customerId]
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(r.reference LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR r.message LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'r.status = ?';
            $params[] = $status;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM client_requests r JOIN customers c ON c.id = r.customer_id WHERE $w", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage);
        $items  = $this->db()->all(
            "SELECT r.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, p.name AS project_name
             FROM client_requests r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN client_projects p ON p.id = r.client_project_id
             WHERE $w ORDER BY r.created_at DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        return $this->db()->first(
            'SELECT r.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    c.business_name AS customer_business, p.name AS project_name, p.live_url AS project_url
             FROM client_requests r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN client_projects p ON p.id = r.client_project_id
             WHERE r.id = ?',
            [$id]
        );
    }

    public function updateRow(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('client_requests', $data, 'id', $id);
    }

    public function deleteRow(int $id): void
    {
        $this->db()->delete('client_requests', 'id', $id);
    }

    /** Sequential, human-readable reference such as TBR-2026-0007. */
    private function nextReference(): string
    {
        $year  = date('Y');
        $count = $this->db()->int('SELECT COUNT(*) FROM client_requests WHERE YEAR(created_at) = ?', [(int) $year]);
        do {
            $ref   = sprintf('TBR-%s-%04d', $year, ++$count);
            $taken = $this->db()->int('SELECT COUNT(*) FROM client_requests WHERE reference = ?', [$ref]) > 0;
        } while ($taken && $count < 100000);
        return $ref;
    }
}
