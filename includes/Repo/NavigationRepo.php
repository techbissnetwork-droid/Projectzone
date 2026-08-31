<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

final class NavigationRepo extends BaseRepo
{
    protected string $table = 'navigation';

    /** @return array<int,array<string,mixed>> top level items with a `children` key */
    public function tree(string $menu = 'primary'): array
    {
        /** @var array<int,array<string,mixed>> $tree */
        $tree = Cache::remember('nav.' . $menu, function () use ($menu): array {
            $rows = $this->db()->all(
                'SELECT * FROM navigation WHERE menu = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC',
                [$menu]
            );
            $byParent = [];
            foreach ($rows as $row) {
                $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
            }
            $top = $byParent[0] ?? [];
            foreach ($top as &$item) {
                $item['children'] = $byParent[(int) $item['id']] ?? [];
            }
            unset($item);
            return $top;
        }, 600);
        return $tree;
    }

    /** @return array<int,array<string,mixed>> flat list for the admin table */
    public function allForMenu(string $menu): array
    {
        return $this->db()->all(
            'SELECT n.*, p.label AS parent_label FROM navigation n
             LEFT JOIN navigation p ON p.id = n.parent_id
             WHERE n.menu = ? ORDER BY COALESCE(n.parent_id, n.id), n.parent_id IS NOT NULL, n.sort_order ASC',
            [$menu]
        );
    }

    /** @return array<int,array<string,mixed>> possible parents (top level only) */
    public function parentOptions(string $menu, ?int $excludeId = null): array
    {
        $sql    = 'SELECT id, label FROM navigation WHERE menu = ? AND parent_id IS NULL';
        $params = [$menu];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        return $this->db()->all($sql . ' ORDER BY sort_order ASC', $params);
    }

    /** @return array<int,string> */
    public function menus(): array
    {
        return ['primary', 'footer', 'legal'];
    }

    public function nextSortForMenu(string $menu, ?int $parentId): int
    {
        if ($parentId === null) {
            return (int) $this->db()->value(
                'SELECT COALESCE(MAX(sort_order),0)+1 FROM navigation WHERE menu = ? AND parent_id IS NULL',
                [$menu],
                1
            );
        }
        return (int) $this->db()->value(
            'SELECT COALESCE(MAX(sort_order),0)+1 FROM navigation WHERE menu = ? AND parent_id = ?',
            [$menu, $parentId],
            1
        );
    }
}
