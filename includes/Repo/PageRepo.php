<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class PageRepo extends BaseRepo
{
    protected string $table = 'pages';
    protected string $orderBy = 'sort_order ASC, title ASC';

    public function publishedBySlug(string $slug): ?array
    {
        return $this->db()->first('SELECT * FROM pages WHERE slug = ? AND is_published = 1', [$slug]);
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM pages WHERE is_published = 1 AND noindex = 0 ORDER BY sort_order');
    }
}
