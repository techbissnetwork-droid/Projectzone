<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class ActivityLog
{
    public static function record(string $action, string $entityType = '', ?int $entityId = null, string $description = ''): void
    {
        try {
            Database::instance()->insert('activity_logs', [
                'admin_id'    => Auth::id() > 0 ? Auth::id() : null,
                'admin_name'  => mb_substr(Auth::name(), 0, 120),
                'action'      => mb_substr($action, 0, 80),
                'entity_type' => mb_substr($entityType, 0, 80),
                'entity_id'   => $entityId,
                'description' => mb_substr($description, 0, 500),
                'ip_address'  => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Logging must never break the action being logged.
        }
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public static function paginate(int $page, int $perPage, string $search = '', string $action = ''): array
    {
        $db     = Database::instance();
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(admin_name LIKE ? OR description LIKE ? OR entity_type LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if ($action !== '') {
            $where[]  = 'action = ?';
            $params[] = $action;
        }
        $w      = implode(' AND ', $where);
        $total  = $db->int("SELECT COUNT(*) FROM activity_logs WHERE $w", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $db->all(
            "SELECT * FROM activity_logs WHERE $w ORDER BY created_at DESC, id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<int,string> */
    public static function actions(): array
    {
        return array_map('strval', Database::instance()->column('SELECT DISTINCT action FROM activity_logs ORDER BY action'));
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 8): array
    {
        return Database::instance()->all(
            'SELECT * FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function prune(int $keepDays = 180): void
    {
        Database::instance()->run(
            'DELETE FROM activity_logs WHERE created_at < ?',
            [date('Y-m-d H:i:s', time() - $keepDays * 86400)]
        );
    }
}
