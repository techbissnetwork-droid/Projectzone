<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class FaqRepo extends BaseRepo
{
    protected string $table = 'faqs';
    protected string $orderBy = 'category ASC, sort_order ASC, id ASC';

    /** @return array<string,array<int,array<string,mixed>>> grouped by category */
    public function grouped(?int $limit = null): array
    {
        $sql = 'SELECT * FROM faqs WHERE is_published = 1 ORDER BY sort_order ASC, id ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        $out = [];
        foreach ($this->db()->all($sql) as $row) {
            $out[(string) $row['category']][] = $row;
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function publishedFlat(int $limit = 8): array
    {
        return $this->db()->all(
            'SELECT * FROM faqs WHERE is_published = 1 ORDER BY sort_order ASC, id ASC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,string> */
    public function categories(): array
    {
        return array_map('strval', $this->db()->column('SELECT DISTINCT category FROM faqs ORDER BY category'));
    }
}
