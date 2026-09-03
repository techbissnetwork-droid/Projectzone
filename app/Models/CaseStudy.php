<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class CaseStudy
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(array $filters = []): array
    {
        $where = ['1 = 1'];
        $bindings = [];
        if (!empty($filters['industry'])) {
            $where[] = 'industry = :industry';
            $bindings['industry'] = $filters['industry'];
        }
        $rows = $this->db->select(
            'SELECT * FROM case_studies WHERE ' . implode(' AND ', $where) . ' ORDER BY featured DESC, published_at DESC',
            $bindings
        );
        return array_map([self::class, 'decode'], $rows);
    }

    /** @return list<array<string,mixed>> */
    public function featured(int $limit = 3): array
    {
        $rows = $this->db->select(
            'SELECT * FROM case_studies ORDER BY featured DESC, published_at DESC LIMIT ' . max(1, $limit)
        );
        return array_map([self::class, 'decode'], $rows);
    }

    public function find(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        $row = $this->db->first('SELECT * FROM case_studies WHERE slug = ? LIMIT 1', [$slug]);
        return $row === null ? null : self::decode($row);
    }

    /** @return list<array<string,mixed>> */
    public function related(string $slug, string $industry, int $limit = 2): array
    {
        $rows = $this->db->select(
            'SELECT * FROM case_studies WHERE slug != ? ORDER BY CASE WHEN industry = ? THEN 0 ELSE 1 END, published_at DESC LIMIT ' . max(1, $limit),
            [$slug, $industry]
        );
        return array_map([self::class, 'decode'], $rows);
    }

    /** @return list<string> */
    public function industries(): array
    {
        return array_column(
            $this->db->select('SELECT DISTINCT industry FROM case_studies ORDER BY industry'),
            'industry'
        );
    }

    public static function decode(array $row): array
    {
        foreach (['metrics', 'approach', 'stack'] as $column) {
            $decoded = json_decode((string) ($row[$column] ?? '[]'), true);
            $row[$column] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }
}
