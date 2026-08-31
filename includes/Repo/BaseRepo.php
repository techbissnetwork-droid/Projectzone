<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;
use Techbiss\Core\Database;

abstract class BaseRepo
{
    protected string $table = '';
    protected string $orderBy = 'sort_order ASC, id ASC';

    protected function db(): Database
    {
        return Database::instance();
    }

    public function table(): string
    {
        return $this->table;
    }

    public function find(int $id): ?array
    {
        return $this->db()->first("SELECT * FROM `{$this->table}` WHERE id = ?", [$id]);
    }


    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db()->all("SELECT * FROM `{$this->table}` ORDER BY {$this->orderBy}");
    }

    /** @return array<int,array<string,mixed>> */
    public function published(?int $limit = null): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE is_published = 1 ORDER BY {$this->orderBy}";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return $this->db()->all($sql);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return $this->db()->int("SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}", $params);
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $now;
        $id = $this->db()->insert($this->table, $data);
        Cache::flush();
        return $id;
    }

    public function updateRow(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update($this->table, $data, 'id', $id);
        Cache::flush();
    }

    public function deleteRow(int $id): void
    {
        $this->db()->delete($this->table, 'id', $id);
        Cache::flush();
    }

    /** Toggle a 0/1 column and return the new value. */
    public function toggle(int $id, string $column): int
    {
        $allowed = ['is_published', 'is_featured', 'is_active', 'is_included', 'is_highlight'];
        if (!in_array($column, $allowed, true)) {
            return 0;
        }
        $current = (int) $this->db()->value("SELECT `{$column}` FROM `{$this->table}` WHERE id = ?", [$id], 0);
        $new     = $current === 1 ? 0 : 1;
        $this->db()->run("UPDATE `{$this->table}` SET `{$column}` = ? WHERE id = ?", [$new, $id]);
        Cache::flush();
        return $new;
    }

    /** Persist a drag-and-drop ordering. @param array<int,int> $ids */
    public function reorder(array $ids): void
    {
        $this->db()->transaction(function (Database $db) use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                $db->run("UPDATE `{$this->table}` SET sort_order = ? WHERE id = ?", [$position + 1, (int) $id]);
            }
        });
        Cache::flush();
    }

    public function nextSortOrder(): int
    {
        return (int) $this->db()->value("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `{$this->table}`", [], 1);
    }

    /** Produce a slug that is unique within the table. */
    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $slug = $slug === '' ? 'item' : $slug;
        $base = $slug;
        $i    = 1;
        while (true) {
            $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE slug = ?";
            $params = [$slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            if ($this->db()->int($sql, $params) === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$i);
        }
    }
}
