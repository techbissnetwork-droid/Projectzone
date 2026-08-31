<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Cache;
use Techbiss\Core\Database;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;

final class SystemController extends BaseAdminController
{
    public function logs(Request $request): void
    {
        $this->authorize('logs.view');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 50);
        $result  = ActivityLog::paginate($page, $perPage, mb_substr($request->queryString('q'), 0, 80), $request->queryString('action'));

        $this->view->render('system/logs', [
            'title'     => 'Activity log',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'actions'   => ActivityLog::actions(),
            'search'    => $request->queryString('q'),
            'action'    => $request->queryString('action'),
        ]);
    }

    public function tools(Request $request): void
    {
        $this->authorize('settings.manage');
        $db = Database::instance();

        $tables = [];
        foreach ($db->all(
            'SELECT table_name AS name, table_rows AS rows_estimate, data_length + index_length AS bytes
             FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
        ) as $t) {
            $tables[] = $t;
        }

        $this->view->render('system/tools', [
            'title'     => 'System & maintenance',
            'tables'    => $tables,
            'php'       => PHP_VERSION,
            'server'    => $db->value('SELECT VERSION()', [], 'unknown'),
            'appVersion'=> App::VERSION,
            'uploads'   => $this->directorySize(App::root() . '/uploads'),
            'cacheDir'  => App::root() . '/storage/cache',
            'exports'   => [
                ['label' => 'Contact messages',  'url' => '/admin/messages/export',   'permission' => 'export.manage'],
                ['label' => 'Quote requests',    'url' => '/admin/quotes/export',     'permission' => 'export.manage'],
                ['label' => 'Customers',         'url' => '/admin/customers/export',  'permission' => 'export.manage'],
                ['label' => 'Purchases',         'url' => '/admin/purchases/export',  'permission' => 'export.manage'],
                ['label' => 'Newsletter list',   'url' => '/admin/subscribers/export','permission' => 'export.manage'],
            ],
        ]);
    }

    public function clearCache(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verify($request);
        Cache::flush();
        ActivityLog::record('cache', 'system', null, 'Cleared the application cache');
        $this->ok('Cache cleared. The public site will rebuild it on the next request.', '/admin/system');
    }

    public function pruneLogs(Request $request): never
    {
        $this->authorize('logs.view');
        $this->verify($request);
        $days = max(7, min(730, $request->int('days', 180)));
        ActivityLog::prune($days);
        ActivityLog::record('prune', 'system', null, 'Pruned activity logs older than ' . $days . ' days');
        $this->ok('Activity log entries older than ' . $days . ' days have been removed.', '/admin/system');
    }

    /** Structural SQL dump of the content tables, for backup purposes. */
    public function exportDatabase(Request $request): never
    {
        $this->authorize('export.manage');
        $db = Database::instance();

        $tables = array_map('strval', $db->column(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
        ));

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="techbiss-backup-' . date('Y-m-d-His') . '.sql"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        echo "-- TECHBISS database export\n-- Generated " . date('c') . "\n";
        echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            $create = $db->first('SHOW CREATE TABLE `' . $table . '`');
            if ($create !== null) {
                echo "DROP TABLE IF EXISTS `$table`;\n";
                echo (string) ($create['Create Table'] ?? '') . ";\n\n";
            }
            // Never export password hashes or session artefacts in a content backup.
            if (in_array($table, ['login_attempts'], true)) {
                continue;
            }
            $rows = $db->all('SELECT * FROM `' . $table . '`');
            foreach (array_chunk($rows, 100) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $cells = array_map(static function ($v) use ($db): string {
                        return $v === null ? 'NULL' : $db->pdo()->quote((string) $v);
                    }, array_values($row));
                    $values[] = '(' . implode(',', $cells) . ')';
                }
                if ($values !== []) {
                    $cols = implode(',', array_map(static fn ($c) => '`' . $c . '`', array_keys($chunk[0])));
                    echo "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $values) . ";\n";
                }
            }
            echo "\n";
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        ActivityLog::record('export', 'system', null, 'Exported a full database backup');
        exit;
    }

    private function directorySize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}
