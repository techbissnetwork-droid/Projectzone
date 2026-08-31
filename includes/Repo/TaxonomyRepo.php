<?php
declare(strict_types=1);

namespace Techbiss\Repo;

/** Simple lookup tables: portfolio and project categories, technologies, blog categories and tags. */
final class TaxonomyRepo extends BaseRepo
{
    protected string $table;

    public function __construct(string $table)
    {
        $allowed = ['portfolio_categories', 'portfolio_technologies', 'project_categories', 'blog_categories', 'blog_tags'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unknown taxonomy table: ' . $table);
        }
        $this->table   = $table;
        $this->orderBy = in_array($table, ['blog_tags'], true) ? 'name ASC' : 'sort_order ASC, name ASC';
    }

    /** @return array<int,array<string,mixed>> */
    public function withCounts(string $countTable, string $foreignKey): array
    {
        $t  = $this->table;
        $ct = preg_match('/^[a-z_]+$/', $countTable) ? $countTable : '';
        $fk = preg_match('/^[a-z_]+$/', $foreignKey) ? $foreignKey : '';
        if ($ct === '' || $fk === '') {
            return $this->all();
        }
        return $this->db()->all(
            "SELECT t.*, (SELECT COUNT(*) FROM `$ct` x WHERE x.`$fk` = t.id) AS usage_count
             FROM `$t` t ORDER BY {$this->orderBy}"
        );
    }

    /** Find an existing row by name or create it — used for free-text tag entry. */
    public function findOrCreate(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $slug = \Techbiss\Core\Str::slug($name);
        $existing = $this->db()->first("SELECT id FROM `{$this->table}` WHERE slug = ?", [$slug]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $data = ['slug' => $slug, 'name' => mb_substr($name, 0, 80)];
        if ($this->table !== 'blog_tags') {
            $data['sort_order']   = $this->nextSortOrder();
            $data['is_published'] = 1;
            $data['created_at']   = date('Y-m-d H:i:s');
            $data['updated_at']   = date('Y-m-d H:i:s');
        }
        if ($this->table === 'portfolio_technologies') {
            unset($data['is_published'], $data['created_at'], $data['updated_at']);
        }
        \Techbiss\Core\Cache::flush();
        return $this->db()->insert($this->table, $data);
    }
}
