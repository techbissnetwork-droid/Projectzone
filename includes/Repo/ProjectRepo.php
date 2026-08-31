<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

/**
 * Premade projects — ready-made builds offered as-is.
 *
 * No price is stored or shown anywhere. Each project is priced in conversation
 * over WhatsApp or email, so there is no figure here to go stale or to imply a
 * saving that was never agreed.
 *
 * Demo links are stored exactly as entered and never fabricated: a project with
 * no demo URL simply shows no demo button.
 */
final class ProjectRepo extends BaseRepo
{
    protected string $table = 'premade_projects';

    private const SELECT_LIST = 'p.*, c.name AS category_name, c.slug AS category_slug,
        i.name AS industry_name, i.slug AS industry_slug';
    private const JOINS = 'FROM premade_projects p
        LEFT JOIN project_categories c ON c.id = p.category_id
        LEFT JOIN industries i ON i.id = p.industry_id';

    /** Sort keys the public listing accepts, mapped to a safe ORDER BY. */
    private const SORTS = [
        'featured' => 'p.is_featured DESC, p.sort_order ASC, p.id DESC',
        'newest'   => 'p.created_at DESC, p.id DESC',
        'name'     => 'p.name ASC',
    ];

    /**
     * Public listing with optional category / industry / search filters.
     *
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(
        int $page,
        int $perPage,
        string $categorySlug = '',
        string $industrySlug = '',
        string $search = '',
        string $sort = 'featured'
    ): array {
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
            $where[]  = '(p.name LIKE ? OR p.tagline LIKE ? OR p.short_description LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);
        $order    = self::SORTS[$sort] ?? self::SORTS['featured'];

        $total  = $this->db()->int('SELECT COUNT(*) ' . self::JOINS . ' WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE ' . $whereSql .
            ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['items' => $this->decorate($items), 'total' => $total];
    }

    /** @return array<int,string> the sort keys the listing will honour */
    public static function sortKeys(): array
    {
        return array_keys(self::SORTS);
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 3): array
    {
        $rows = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS .
            ' WHERE p.is_published = 1 ORDER BY p.is_featured DESC, p.sort_order ASC, p.id DESC LIMIT ' . max(1, $limit)
        );
        return $this->decorate($rows);
    }

    public function publishedBySlug(string $slug): ?array
    {
        $row = $this->db()->first(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE p.slug = ? AND p.is_published = 1',
            [$slug]
        );
        if ($row === null) {
            return null;
        }
        $row['features']     = $this->features((int) $row['id']);
        $row['images']       = $this->images((int) $row['id']);
        $row['technologies'] = $this->technologyNames((int) $row['id']);
        return $row;
    }

    public function findWithJoins(int $id): ?array
    {
        return $this->db()->first('SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . ' WHERE p.id = ?', [$id]);
    }

    /** Attach the technology list to a set of rows. */
    private function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['technologies'] = $this->technologyNames((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function adminList(string $search = '', string $status = '', int $categoryId = 0): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(p.name LIKE ? OR p.tagline LIKE ?)';
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
        $rows = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ',
                (SELECT COUNT(*) FROM project_images pi WHERE pi.project_id = p.id) AS image_count,
                (SELECT COUNT(*) FROM project_orders po WHERE po.project_id = p.id) AS order_count '
            . self::JOINS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort_order ASC, p.id DESC',
            $params
        );
        return $rows;
    }

    // -----------------------------------------------------------------
    // Features
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function features(int $projectId): array
    {
        return $this->db()->all(
            'SELECT * FROM project_features WHERE project_id = ? ORDER BY sort_order ASC, id ASC',
            [$projectId]
        );
    }

    public function replaceFeatures(int $projectId, array $rows): void
    {
        $this->db()->transaction(function ($db) use ($projectId, $rows): void {
            $db->run('DELETE FROM project_features WHERE project_id = ?', [$projectId]);
            $order = 0;
            foreach ($rows as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $db->insert('project_features', [
                    'project_id'  => $projectId,
                    'title'       => mb_substr($title, 0, 190),
                    'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 500),
                    'is_included' => !empty($row['is_included']) ? 1 : 0,
                    'sort_order'  => ++$order,
                ]);
            }
        });
        Cache::flush();
    }

    // -----------------------------------------------------------------
    // Gallery
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function images(int $projectId): array
    {
        return $this->db()->all(
            'SELECT * FROM project_images WHERE project_id = ? ORDER BY sort_order ASC, id ASC',
            [$projectId]
        );
    }

    public function addImage(int $projectId, string $path, string $alt = '', string $caption = ''): int
    {
        $order = (int) $this->db()->value(
            'SELECT COALESCE(MAX(sort_order),0)+1 FROM project_images WHERE project_id = ?',
            [$projectId],
            1
        );
        Cache::flush();
        return $this->db()->insert('project_images', [
            'project_id' => $projectId,
            'path'       => $path,
            'alt_text'   => mb_substr($alt, 0, 255),
            'caption'    => mb_substr($caption, 0, 255),
            'sort_order' => $order,
        ]);
    }

    public function deleteImage(int $imageId, int $projectId): void
    {
        $this->db()->run('DELETE FROM project_images WHERE id = ? AND project_id = ?', [$imageId, $projectId]);
        Cache::flush();
    }

    public function reorderImages(int $projectId, array $ids): void
    {
        $this->db()->transaction(function ($db) use ($projectId, $ids): void {
            foreach (array_values($ids) as $pos => $id) {
                $db->run(
                    'UPDATE project_images SET sort_order = ? WHERE id = ? AND project_id = ?',
                    [$pos + 1, (int) $id, $projectId]
                );
            }
        });
        Cache::flush();
    }

    // -----------------------------------------------------------------
    // Technologies (shared with the portfolio taxonomy)
    // -----------------------------------------------------------------

    /** @return array<int,string> */
    public function technologyNames(int $projectId): array
    {
        return array_map('strval', $this->db()->column(
            'SELECT t.name FROM portfolio_technologies t
             JOIN project_technology_map m ON m.technology_id = t.id
             WHERE m.project_id = ? ORDER BY t.sort_order ASC, t.name ASC',
            [$projectId]
        ));
    }

    /** @return array<int,int> */
    public function technologyIds(int $projectId): array
    {
        return array_map('intval', $this->db()->column(
            'SELECT technology_id FROM project_technology_map WHERE project_id = ?',
            [$projectId]
        ));
    }

    public function syncTechnologies(int $projectId, array $ids): void
    {
        $this->db()->transaction(function ($db) use ($projectId, $ids): void {
            $db->run('DELETE FROM project_technology_map WHERE project_id = ?', [$projectId]);
            foreach (array_unique(array_map('intval', $ids)) as $tid) {
                if ($tid > 0) {
                    $db->insert('project_technology_map', ['project_id' => $projectId, 'technology_id' => $tid]);
                }
            }
        });
        Cache::flush();
    }

    // -----------------------------------------------------------------

    /** Projects sharing a category or industry. @return array<int,array<string,mixed>> */
    public function related(array $project, int $limit = 3): array
    {
        $rows = $this->db()->all(
            'SELECT ' . self::SELECT_LIST . ' ' . self::JOINS . '
             WHERE p.is_published = 1 AND p.id <> ?
               AND (p.category_id = ? OR p.industry_id = ?)
             ORDER BY p.is_featured DESC, p.sort_order ASC LIMIT ' . max(1, $limit),
            [(int) $project['id'], $project['category_id'] ?? 0, $project['industry_id'] ?? 0]
        );
        return $this->decorate($rows);
    }

    public function incrementViews(int $id): void
    {
        $this->db()->run('UPDATE premade_projects SET view_count = view_count + 1 WHERE id = ?', [$id]);
    }

    /** Categories that actually hold a published project. @return array<int,array<string,mixed>> */
    public function activeCategories(): array
    {
        return $this->db()->all(
            'SELECT c.*, (SELECT COUNT(*) FROM premade_projects p WHERE p.category_id = c.id AND p.is_published = 1) AS project_count
             FROM project_categories c
             WHERE c.is_published = 1
               AND EXISTS (SELECT 1 FROM premade_projects p WHERE p.category_id = c.id AND p.is_published = 1)
             ORDER BY c.sort_order ASC'
        );
    }

    public function publishedCount(): int
    {
        return $this->db()->int('SELECT COUNT(*) FROM premade_projects WHERE is_published = 1');
    }

    /** Copy a project with its features, images and technologies. */
    public function duplicate(int $id): ?int
    {
        $row = $this->find($id);
        if ($row === null) {
            return null;
        }
        return $this->db()->transaction(function ($db) use ($row, $id): int {
            unset($row['id'], $row['created_at'], $row['updated_at']);
            $row['name']         = mb_substr($row['name'] . ' (copy)', 0, 190);
            $row['slug']         = $this->uniqueSlug($row['slug'] . '-copy');
            $row['is_published'] = 0;
            $row['is_featured']  = 0;
            $row['view_count']   = 0;
            $row['sort_order']   = $this->nextSortOrder();
            $row['created_at']   = date('Y-m-d H:i:s');
            $row['updated_at']   = date('Y-m-d H:i:s');
            $newId = $db->insert('premade_projects', $row);

            foreach ($this->features($id) as $f) {
                $db->insert('project_features', [
                    'project_id'  => $newId,
                    'title'       => $f['title'],
                    'description' => $f['description'],
                    'is_included' => $f['is_included'],
                    'sort_order'  => $f['sort_order'],
                ]);
            }
            foreach ($this->images($id) as $img) {
                $db->insert('project_images', [
                    'project_id' => $newId,
                    'path'       => $img['path'],
                    'alt_text'   => $img['alt_text'],
                    'caption'    => $img['caption'],
                    'sort_order' => $img['sort_order'],
                ]);
            }
            foreach ($this->technologyIds($id) as $tid) {
                $db->insert('project_technology_map', ['project_id' => $newId, 'technology_id' => $tid]);
            }
            Cache::flush();
            return $newId;
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM premade_projects WHERE is_published = 1 ORDER BY sort_order');
    }

    /** @return array<int,array{id:int,name:string}> */
    public function options(): array
    {
        return $this->db()->all('SELECT id, name FROM premade_projects WHERE is_published = 1 ORDER BY sort_order ASC');
    }
}
