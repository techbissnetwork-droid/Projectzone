<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

final class CustomerRepo
{
    private function db(): Database
    {
        return Database::instance();
    }

    public function find(int $id): ?array
    {
        return $this->db()->first(
            'SELECT c.*, i.name AS industry_name FROM customers c LEFT JOIN industries i ON i.id = c.industry_id WHERE c.id = ?',
            [$id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db()->first('SELECT * FROM customers WHERE email = ?', [$email]);
    }

    /** Create the customer, or update the record we already hold for that email. */
    public function upsert(array $data): int
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->findByEmail((string) $data['email']);
        if ($existing !== null) {
            $update = array_filter([
                'name'          => $data['name'] ?? '',
                'business_name' => $data['business_name'] ?? '',
                'phone'         => $data['phone'] ?? '',
                'country'       => $data['country'] ?? '',
            ], static fn ($v) => $v !== '' && $v !== null);
            if ($update !== []) {
                $update['updated_at'] = $now;
                $this->db()->update('customers', $update, 'id', (int) $existing['id']);
            }
            return (int) $existing['id'];
        }
        return $this->db()->insert('customers', $data + ['status' => 'lead', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('customers', $data, 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db()->delete('customers', 'id', $id);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.business_name LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'c.status = ?';
            $params[] = $status;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM customers c WHERE $w", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage); // clamp page: an absurd ?page must not overflow the int product into a float and corrupt the SQL OFFSET
        $items  = $this->db()->all(
            "SELECT c.*, i.name AS industry_name
             FROM customers c LEFT JOIN industries i ON i.id = c.industry_id
             WHERE $w ORDER BY c.created_at DESC, c.id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function export(): array
    {
        return $this->db()->all('SELECT name, business_name, email, phone, country, status, created_at FROM customers ORDER BY created_at DESC');
    }

    public function count(string $where = '1', array $params = []): int
    {
        return $this->db()->int("SELECT COUNT(*) FROM customers WHERE $where", $params);
    }
}
