<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Resource
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $where = ['1 = 1'];
        $bindings = [];
        if (!empty($filters['topic'])) {
            $where[] = 'topic = :topic';
            $bindings['topic'] = $filters['topic'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'type = :type';
            $bindings['type'] = $filters['type'];
        }
        return $this->db->select(
            'SELECT * FROM resources WHERE ' . implode(' AND ', $where)
            . ' ORDER BY featured DESC, published_at DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $bindings
        );
    }

    public function count(array $filters = []): int
    {
        $where = ['1 = 1'];
        $bindings = [];
        if (!empty($filters['topic'])) {
            $where[] = 'topic = :topic';
            $bindings['topic'] = $filters['topic'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'type = :type';
            $bindings['type'] = $filters['type'];
        }
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM resources WHERE ' . implode(' AND ', $where),
            $bindings,
            0
        );
    }

    public function find(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM resources WHERE slug = ? LIMIT 1', [$slug]);
    }

    /** @return list<array<string,mixed>> */
    public function related(string $slug, string $topic, int $limit = 3): array
    {
        return $this->db->select(
            'SELECT * FROM resources WHERE slug != ? ORDER BY CASE WHEN topic = ? THEN 0 ELSE 1 END, published_at DESC LIMIT ' . max(1, $limit),
            [$slug, $topic]
        );
    }

    /** @return list<string> */
    public function topics(): array
    {
        return array_column($this->db->select('SELECT DISTINCT topic FROM resources ORDER BY topic'), 'topic');
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_column($this->db->select('SELECT DISTINCT type FROM resources ORDER BY type'), 'type');
    }
}
