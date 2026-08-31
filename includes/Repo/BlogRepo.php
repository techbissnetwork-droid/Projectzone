<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class BlogRepo extends BaseRepo
{
    protected string $table = 'blog_posts';
    protected string $orderBy = 'published_at DESC, id DESC';

    private const SELECT = 'p.*, c.name AS category_name, c.slug AS category_slug';
    private const JOINS  = 'FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id';

    /**
     * A post is publicly visible when it is marked published (or was scheduled
     * for a moment that has now passed) and its publish date is not in the future.
     */
    private function liveCondition(): string
    {
        return "(p.status = 'published' OR (p.status = 'scheduled' AND p.published_at IS NOT NULL AND p.published_at <= ?))
                AND (p.published_at IS NULL OR p.published_at <= ?)";
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $categorySlug = '', string $tagSlug = '', string $search = ''): array
    {
        $now    = date('Y-m-d H:i:s');
        $where  = ['(' . $this->liveCondition() . ')'];
        $params = [$now, $now];
        $joins  = self::JOINS;

        if ($categorySlug !== '') {
            $where[]  = 'c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($tagSlug !== '') {
            $joins   .= ' JOIN blog_post_tags bpt ON bpt.post_id = p.id JOIN blog_tags t ON t.id = bpt.tag_id';
            $where[]  = 't.slug = ?';
            $params[] = $tagSlug;
        }
        if ($search !== '') {
            $where[]  = '(p.title LIKE ? OR p.excerpt LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = implode(' AND ', $where);

        $total  = $this->db()->int("SELECT COUNT(DISTINCT p.id) $joins WHERE $whereSql", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage); // clamp page: an absurd ?page must not overflow the int product into a float and corrupt the SQL OFFSET
        $items  = $this->db()->all(
            'SELECT DISTINCT ' . self::SELECT . " $joins WHERE $whereSql ORDER BY p.published_at DESC, p.id DESC LIMIT "
            . (int) $perPage . ' OFFSET ' . $offset,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function publishedBySlug(string $slug): ?array
    {
        $now  = date('Y-m-d H:i:s');
        $post = $this->db()->first(
            'SELECT ' . self::SELECT . ' ' . self::JOINS . ' WHERE p.slug = ? AND (' . $this->liveCondition() . ')',
            [$slug, $now, $now]
        );
        if ($post !== null) {
            $post['tags'] = $this->tagsFor((int) $post['id']);
        }
        return $post;
    }

    /** @return array<int,array<string,mixed>> */
    public function latest(int $limit = 3): array
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->all(
            'SELECT ' . self::SELECT . ' ' . self::JOINS . ' WHERE (' . $this->liveCondition() . ')
             ORDER BY p.is_featured DESC, p.published_at DESC LIMIT ' . max(1, $limit),
            [$now, $now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function related(array $post, int $limit = 3): array
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->all(
            'SELECT ' . self::SELECT . ' ' . self::JOINS . ' WHERE (' . $this->liveCondition() . ')
             AND p.id <> ? AND (p.category_id = ? OR p.category_id IS NOT NULL)
             ORDER BY (p.category_id = ?) DESC, p.published_at DESC LIMIT ' . max(1, $limit),
            [$now, $now, (int) $post['id'], $post['category_id'] ?? 0, $post['category_id'] ?? 0]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function adminList(string $search = '', string $status = '', int $categoryId = 0): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = 'p.title LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if (in_array($status, ['draft', 'scheduled', 'published'], true)) {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }
        if ($categoryId > 0) {
            $where[]  = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        return $this->db()->all(
            'SELECT ' . self::SELECT . ', a.name AS author_display ' . self::JOINS . '
             LEFT JOIN admins a ON a.id = p.author_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function tagsFor(int $postId): array
    {
        return $this->db()->all(
            'SELECT t.* FROM blog_tags t JOIN blog_post_tags m ON m.tag_id = t.id WHERE m.post_id = ? ORDER BY t.name',
            [$postId]
        );
    }

    /** @return array<int,int> */
    public function tagIds(int $postId): array
    {
        return array_map('intval', $this->db()->column('SELECT tag_id FROM blog_post_tags WHERE post_id = ?', [$postId]));
    }

    public function syncTags(int $postId, array $tagIds): void
    {
        $this->db()->transaction(function ($db) use ($postId, $tagIds): void {
            $db->run('DELETE FROM blog_post_tags WHERE post_id = ?', [$postId]);
            foreach (array_unique(array_map('intval', $tagIds)) as $tid) {
                if ($tid > 0) {
                    $db->insert('blog_post_tags', ['post_id' => $postId, 'tag_id' => $tid]);
                }
            }
        });
        \Techbiss\Core\Cache::flush();
    }

    public function incrementViews(int $id): void
    {
        $this->db()->run('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?', [$id]);
    }

    /** Categories that actually contain live posts. @return array<int,array<string,mixed>> */
    public function activeCategories(): array
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->all(
            "SELECT c.*, COUNT(p.id) AS post_count FROM blog_categories c
             JOIN blog_posts p ON p.category_id = c.id
               AND (p.status = 'published' OR (p.status = 'scheduled' AND p.published_at <= ?))
               AND (p.published_at IS NULL OR p.published_at <= ?)
             WHERE c.is_published = 1
             GROUP BY c.id ORDER BY c.sort_order ASC",
            [$now, $now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->all(
            "SELECT slug, updated_at FROM blog_posts
             WHERE (status = 'published' OR (status = 'scheduled' AND published_at <= ?))
               AND (published_at IS NULL OR published_at <= ?)
             ORDER BY published_at DESC",
            [$now, $now]
        );
    }
}
