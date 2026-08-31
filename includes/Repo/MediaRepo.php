<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

final class MediaRepo
{
    private function db(): Database
    {
        return Database::instance();
    }

    public function create(array $data): int
    {
        return $this->db()->insert('media', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public function find(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM media WHERE id = ?', [$id]);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $search = '', string $folder = '', string $type = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(original_name LIKE ? OR alt_text LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($folder !== '') {
            $where[]  = 'folder = ?';
            $params[] = $folder;
        }
        if ($type === 'image') {
            $where[] = "mime_type LIKE 'image/%'";
        } elseif ($type === 'document') {
            $where[] = "mime_type NOT LIKE 'image/%'";
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM media WHERE $w", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage); // clamp page: an absurd ?page must not overflow the int product into a float and corrupt the SQL OFFSET
        $items  = $this->db()->all(
            "SELECT * FROM media WHERE $w ORDER BY created_at DESC, id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function update(int $id, array $data): void
    {
        $this->db()->update('media', $data, 'id', $id);
    }

    public function delete(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }
        $root = dirname(__DIR__, 2);
        foreach ([$row['path'], $row['thumb_path']] as $rel) {
            $rel = (string) $rel;
            if ($rel === '') {
                continue;
            }
            $abs = $root . '/' . ltrim($rel, '/');
            // Only ever unlink inside the uploads directory.
            $real = realpath($abs);
            $base = realpath($root . '/uploads');
            if ($real !== false && $base !== false && str_starts_with($real, $base) && is_file($real)) {
                @unlink($real);
            }
        }
        $this->db()->delete('media', 'id', $id);
        return true;
    }

    /** @return array<int,string> */
    public function folders(): array
    {
        $rows = array_map('strval', $this->db()->column('SELECT DISTINCT folder FROM media ORDER BY folder'));
        return array_values(array_unique(array_merge(['general', 'portfolio', 'services', 'blog', 'brand', 'team'], $rows)));
    }

    public function totalBytes(): int
    {
        return (int) $this->db()->value('SELECT COALESCE(SUM(size_bytes),0) FROM media', [], 0);
    }

    public function count(): int
    {
        return $this->db()->int('SELECT COUNT(*) FROM media');
    }
}
