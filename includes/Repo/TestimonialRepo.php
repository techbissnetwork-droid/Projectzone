<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class TestimonialRepo extends BaseRepo
{
    protected string $table = 'testimonials';

    /** @return array<int,array<string,mixed>> */
    public function publishedWithProject(?int $limit = null): array
    {
        $sql = 'SELECT t.*, p.title AS project_title, p.slug AS project_slug
                FROM testimonials t LEFT JOIN portfolio p ON p.id = t.portfolio_id
                WHERE t.is_published = 1
                ORDER BY t.is_featured DESC, t.sort_order ASC, t.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return $this->db()->all($sql);
    }

    /** @return array<int,array<string,mixed>> */
    public function adminList(): array
    {
        return $this->db()->all(
            'SELECT t.*, p.title AS project_title FROM testimonials t
             LEFT JOIN portfolio p ON p.id = t.portfolio_id
             ORDER BY t.sort_order ASC, t.id DESC'
        );
    }
}
