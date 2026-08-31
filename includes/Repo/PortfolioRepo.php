<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

final class PortfolioRepo extends BaseRepo
{
    protected string $table = 'portfolio';

    private const SELECT_LIST = 'p.*, c.name AS category_name, c.slug AS category_slug, i.name AS industry_name, i.slug AS industry_slug';
    private const JOINS = 'FROM portfolio p
        LEFT JOIN portfolio_categories c ON c.id = p.category_id
        LEFT JOIN industries i ON i.id = p.industry_id';

    /**
     * Public listing with optional category/industry filters.
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(int $page, int $perPage, string $categorySlug = '', string $industrySlug = '', string $search = ''): array
    {
        $where  = ['p.is_published = 1'];
        $params = [];
        if ($categorySlug !== '') {
            $where[]  = 'c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($industrySlug !== '') {
            $where[]  = 'i.slug = ?';
            $params[] = $industrySlug;
        }
        if ($search !== '') {
            $where[]  = '(p.title LIKE ? OR p.client_name LIKE ? OR p.short_description LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = $this->db()->int('SELECT COUNT(*) ' . self::JOINS . ' WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE ' . $whereSql .
            ' ORDER BY p.is_featured DESC, p.sort_order ASC, p.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        );
        foreach ($items as &$item) {
            $item['technologies'] = $this->technologyNames((int) $item['id']);
        }
        unset($item);

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 6): array
    {
        $rows = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS .
            ' WHERE p.is_published = 1 ORDER BY p.is_featured DESC, p.sort_order ASC, p.id DESC LIMIT ' . max(1, $limit)
        );
        foreach ($rows as &$row) {
            $row['technologies'] = $this->technologyNames((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    public function publishedBySlug(string $slug): ?array
    {
        return $this->db()->first(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE p.slug = ? AND p.is_published = 1',
            [$slug]
        );
    }

    public function findWithJoins(int $id): ?array
    {
        return $this->db()->first('SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE p.id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function adminList(string $search = '', string $status = '', int $categoryId = 0): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(p.title LIKE ? OR p.client_name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($status === 'published') {
            $where[] = 'p.is_published = 1';
        } elseif ($status === 'draft') {
            $where[] = 'p.is_published = 0';
        } elseif ($status === 'featured') {
            $where[] = 'p.is_featured = 1';
        }
        if ($categoryId > 0) {
            $where[]  = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        return $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ', (SELECT COUNT(*) FROM portfolio_images pi WHERE pi.portfolio_id = p.id) AS image_count '
            . self::JOINS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort_order ASC, p.id DESC',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function images(int $portfolioId): array
    {
        return $this->db()->all(
            'SELECT * FROM portfolio_images WHERE portfolio_id = ? ORDER BY sort_order ASC, id ASC',
            [$portfolioId]
        );
    }

    public function addImage(int $portfolioId, string $path, string $alt = '', string $caption = ''): int
    {
        $order = (int) $this->db()->value(
            'SELECT COALESCE(MAX(sort_order),0)+1 FROM portfolio_images WHERE portfolio_id = ?',
            [$portfolioId],
            1
        );
        Cache::flush();
        return $this->db()->insert('portfolio_images', [
            'portfolio_id' => $portfolioId,
            'path'         => $path,
            'alt_text'     => mb_substr($alt, 0, 255),
            'caption'      => mb_substr($caption, 0, 255),
            'sort_order'   => $order,
        ]);
    }

    public function deleteImage(int $imageId, int $portfolioId): void
    {
        $this->db()->run('DELETE FROM portfolio_images WHERE id = ? AND portfolio_id = ?', [$imageId, $portfolioId]);
        Cache::flush();
    }

    public function reorderImages(int $portfolioId, array $ids): void
    {
        $this->db()->transaction(function ($db) use ($portfolioId, $ids): void {
            foreach (array_values($ids) as $pos => $id) {
                $db->run(
                    'UPDATE portfolio_images SET sort_order = ? WHERE id = ? AND portfolio_id = ?',
                    [$pos + 1, (int) $id, $portfolioId]
                );
            }
        });
        Cache::flush();
    }

    /** @return array<int,string> */
    public function technologyNames(int $portfolioId): array
    {
        return array_map('strval', $this->db()->column(
            'SELECT t.name FROM portfolio_technologies t
             JOIN portfolio_technology_map m ON m.technology_id = t.id
             WHERE m.portfolio_id = ? ORDER BY t.sort_order ASC, t.name ASC',
            [$portfolioId]
        ));
    }

    /** @return array<int,int> */
    public function technologyIds(int $portfolioId): array
    {
        return array_map('intval', $this->db()->column(
            'SELECT technology_id FROM portfolio_technology_map WHERE portfolio_id = ?',
            [$portfolioId]
        ));
    }

    public function syncTechnologies(int $portfolioId, array $ids): void
    {
        $this->db()->transaction(function ($db) use ($portfolioId, $ids): void {
            $db->run('DELETE FROM portfolio_technology_map WHERE portfolio_id = ?', [$portfolioId]);
            foreach (array_unique(array_map('intval', $ids)) as $tid) {
                if ($tid > 0) {
                    $db->insert('portfolio_technology_map', ['portfolio_id' => $portfolioId, 'technology_id' => $tid]);
                }
            }
        });
        Cache::flush();
    }

    /** @return array<int,int> */
    public function serviceIds(int $portfolioId): array
    {
        return array_map('intval', $this->db()->column(
            'SELECT service_id FROM portfolio_services WHERE portfolio_id = ?',
            [$portfolioId]
        ));
    }

    public function syncServices(int $portfolioId, array $ids): void
    {
        $this->db()->transaction(function ($db) use ($portfolioId, $ids): void {
            $db->run('DELETE FROM portfolio_services WHERE portfolio_id = ?', [$portfolioId]);
            foreach (array_unique(array_map('intval', $ids)) as $sid) {
                if ($sid > 0) {
                    $db->insert('portfolio_services', ['portfolio_id' => $portfolioId, 'service_id' => $sid]);
                }
            }
        });
        Cache::flush();
    }

    /** Projects sharing a category or industry with the given project. @return array<int,array<string,mixed>> */
    public function related(array $project, int $limit = 3): array
    {
        return $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . '
             WHERE p.is_published = 1 AND p.id <> ?
               AND (p.category_id = ? OR p.industry_id = ?)
             ORDER BY p.is_featured DESC, p.sort_order ASC LIMIT ' . max(1, $limit),
            [(int) $project['id'], $project['category_id'] ?? 0, $project['industry_id'] ?? 0]
        );
    }

    public function incrementViews(int $id): void
    {
        $this->db()->run('UPDATE portfolio SET view_count = view_count + 1 WHERE id = ?', [$id]);
    }

    /** Copy a project, its images, technologies and services. */
    public function duplicate(int $id): ?int
    {
        $row = $this->find($id);
        if ($row === null) {
            return null;
        }
        return $this->db()->transaction(function ($db) use ($row, $id): int {
            unset($row['id'], $row['created_at'], $row['updated_at']);
            $row['title']        = mb_substr($row['title'] . ' (copy)', 0, 190);
            $row['slug']         = $this->uniqueSlug($row['slug'] . '-copy');
            $row['is_published'] = 0;
            $row['is_featured']  = 0;
            $row['view_count']   = 0;
            $row['sort_order']   = $this->nextSortOrder();
            $row['created_at']   = date('Y-m-d H:i:s');
            $row['updated_at']   = date('Y-m-d H:i:s');
            $newId = $db->insert('portfolio', $row);

            foreach ($this->images($id) as $img) {
                $db->insert('portfolio_images', [
                    'portfolio_id' => $newId,
                    'path'         => $img['path'],
                    'alt_text'     => $img['alt_text'],
                    'caption'      => $img['caption'],
                    'sort_order'   => $img['sort_order'],
                ]);
            }
            foreach ($this->technologyIds($id) as $tid) {
                $db->insert('portfolio_technology_map', ['portfolio_id' => $newId, 'technology_id' => $tid]);
            }
            foreach ($this->serviceIds($id) as $sid) {
                $db->insert('portfolio_services', ['portfolio_id' => $newId, 'service_id' => $sid]);
            }
            \Techbiss\Core\Cache::flush();
            return $newId;
        });
    }

    /** @return array<int,array<string,mixed>> categories that actually have published projects */
    public function activeCategories(): array
    {
        return $this->db()->all(
            'SELECT c.* FROM portfolio_categories c
             WHERE c.is_published = 1 AND EXISTS (SELECT 1 FROM portfolio p WHERE p.category_id = c.id AND p.is_published = 1)
             ORDER BY c.sort_order ASC'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM portfolio WHERE is_published = 1 ORDER BY sort_order');
    }
}
