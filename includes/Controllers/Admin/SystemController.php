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
            'security'  => \Techbiss\Core\Cache::get('security_audit'),
            'migration' => $this->migrationStatus(),
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

    /**
     * Check that the shipped protections are actually in force on this server.
     *
     * Everything that hides config/, database/, storage/ and the rest is done
     * with .htaccess. On a host with AllowOverride None, or on nginx, those
     * files are served in full and nothing in the application would notice. So
     * rather than assume, this fetches the URLs over HTTP exactly as a visitor
     * would and reports what actually came back.
     *
     * Only fixed, hardcoded paths on this site's own address are requested;
     * nothing here takes a target from the request.
     *
     * @return array<int,array{label:string,detail:string,status:string,note:string}>
     */
    private function securityAudit(): array
    {
        $base    = rtrim(App::siteUrl(), '/');
        $results = [];

        $exposed = [
            '/config/config.php'        => 'Your database password and application key',
            '/config/config.sample.php' => 'The configuration template',
            '/database/schema.sql'      => 'Your full database structure',
            '/database/seed.sql'        => 'The seed data',
            '/includes/helpers.php'     => 'Application source code',
            '/storage/logs/'            => 'Error and mail logs',
            '/tools/'                   => 'Build scripts',
            '/uploads/'                 => 'A listing of every uploaded file',
        ];

        $reachable = [];
        $unknown   = 0;
        foreach ($exposed as $path => $what) {
            $code = $this->probe($base . $path);
            if ($code === null) {
                $unknown++;
            } elseif ($code === 200) {
                $reachable[] = $path . ' — ' . $what;
            }
        }

        if ($unknown === count($exposed)) {
            $results[] = [
                'label'  => 'Private files',
                'detail' => 'Could not be checked',
                'status' => 'unknown',
                'note'   => 'This server could not make a request to itself, so the check could not run. '
                          . 'Open ' . $base . '/config/config.php in a browser: it must not show you anything.',
            ];
        } elseif ($reachable !== []) {
            $results[] = [
                'label'  => 'Private files',
                'detail' => count($reachable) . ' exposed',
                'status' => 'fail',
                'note'   => 'These are downloadable by anyone: ' . implode('; ', $reachable)
                          . '. The .htaccess files that block them are being ignored — ask your host to enable '
                          . 'AllowOverride All, or add the same rules to your server configuration.',
            ];
        } else {
            $results[] = [
                'label'  => 'Private files',
                'detail' => 'Not reachable',
                'status' => 'pass',
                'note'   => 'config/, database/, includes/, storage/, tools/ and the uploads listing all refuse '
                          . 'direct requests.',
            ];
        }

        // The installer must not survive setup.
        $installer = App::root() . '/install.php';
        $results[] = is_file($installer)
            ? ['label' => 'Setup wizard', 'detail' => 'Still present', 'status' => 'fail',
               'note'  => 'install.php is still on the server. It refuses to run while an administrator exists, but '
                        . 'there is no reason to leave it there. Delete it.']
            : ['label' => 'Setup wizard', 'detail' => 'Removed', 'status' => 'pass',
               'note'  => 'install.php is no longer on the server.'];

        // HTTPS, and the cookie flag that depends on it.
        $https  = str_starts_with($base, 'https://');
        $secure = (bool) (App::config('security.cookie_secure') ?? false);
        if (!$https) {
            $results[] = ['label' => 'HTTPS', 'detail' => 'Not in use', 'status' => 'fail',
                'note' => 'Sign-in details and everything customers submit travel in the clear. Install a certificate, '
                        . 'then uncomment the HTTPS redirect in .htaccess and set cookie_secure to true in config.php.'];
        } elseif (!$secure) {
            $results[] = ['label' => 'HTTPS', 'detail' => 'On, cookie not marked secure', 'status' => 'warn',
                'note' => 'The site is served over HTTPS but cookie_secure is false in config/config.php, so the '
                        . 'session cookie may still be sent over a plain connection. Set it to true.'];
        } else {
            $results[] = ['label' => 'HTTPS', 'detail' => 'In use', 'status' => 'pass',
                'note' => 'The session cookie is marked secure.'];
        }

        // Debug output must never reach visitors.
        $debug = (bool) (App::config('site.debug') ?? false);
        $results[] = $debug
            ? ['label' => 'Debug mode', 'detail' => 'On', 'status' => 'fail',
               'note'  => 'PHP errors are being printed to the page, which shows visitors your file paths and queries. '
                        . 'Set debug to false in config/config.php.']
            : ['label' => 'Debug mode', 'detail' => 'Off', 'status' => 'pass',
               'note'  => 'Errors are written to storage/logs and never shown to visitors.'];

        return $results;
    }

    /**
     * Fetch one of our own URLs and return its status code, or null if the
     * request could not be made at all. Short timeout: this runs while an
     * administrator waits for the page.
     */
    private function probe(string $url): ?int
    {
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 2,
                'ignore_errors'   => true,
                'follow_location' => 0,
                'header'          => "User-Agent: TECHBISS-self-check\r\n",
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $headers = @get_headers($url, false, $context);
        if ($headers === false || $headers === null || !isset($headers[0])) {
            return null;
        }
        return preg_match('#\s(\d{3})\s#', ' ' . $headers[0] . ' ', $m) ? (int) $m[1] : null;
    }

    /**
     * Run the security check now and store the result.
     *
     * Deliberately on demand: it makes real HTTP requests to this same site, so
     * running it on every page load would both slow the page down and, on a
     * server with one PHP worker, block waiting for itself.
     */
    public function recheckSecurity(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verify($request);

        // Release the session file lock first. Without this the request we make
        // to ourselves blocks waiting for the lock this request is holding, and
        // every probe times out for a reason that has nothing to do with the
        // server's actual configuration.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $result = $this->securityAudit();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        \Techbiss\Core\Cache::put('security_audit', $result, 86400);
        ActivityLog::record('check', 'system', null, 'Ran the security check');
        $this->ok('Security check complete.', '/admin/system');
    }

    /**
     * What the database is missing compared to the current schema.
     *
     * Read-only, and cheap enough to run on every page load: it is a handful of
     * information_schema queries against a file already on disk.
     *
     * @return array{pending:int,items:array<int,string>,mismatched:array<int,string>,error:string}
     */
    private function migrationStatus(): array
    {
        try {
            $migrator = new \Techbiss\Core\Migrator(
                Database::instance()->pdo(),
                App::root() . '/database/schema.sql'
            );
            $p = $migrator->pending();
        } catch (\Throwable $e) {
            return ['pending' => 0, 'items' => [], 'mismatched' => [], 'error' => $e->getMessage()];
        }

        $items = [];
        foreach ($p['tables'] as $t) {
            $items[] = 'New table: ' . $t['name'];
        }
        foreach ($p['columns'] as $c) {
            $items[] = 'New field: ' . $c['table'] . '.' . $c['column'];
        }
        foreach ($p['indexes'] as $i) {
            $items[] = 'New index: ' . $i['name'] . ' on ' . $i['table'];
        }
        foreach ($p['data'] as $d) {
            $items[] = $d['label'];
        }

        return [
            'pending'    => count($items),
            'items'      => $items,
            'mismatched' => $p['mismatched'],
            'error'      => '',
        ];
    }

    /**
     * Apply the pending database changes.
     *
     * Additive only — new tables, fields, indexes and the permission rows a new
     * feature needs. Nothing existing is dropped, re-typed or overwritten, so
     * this cannot lose content.
     */
    public function migrate(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verify($request);

        try {
            $migrator = new \Techbiss\Core\Migrator(
                Database::instance()->pdo(),
                App::root() . '/database/schema.sql'
            );
            $log = $migrator->apply();
            // Seeded wording this release changed, but only where the text is
            // still exactly as installed — anything edited here is left alone.
            $copy = $migrator->refreshCopy();
        } catch (\Throwable $e) {
            ActivityLog::record('migrate', 'system', null, 'Database update failed: ' . $e->getMessage());
            flash('error', 'The update stopped: ' . $e->getMessage()
                . ' Nothing after that point was applied — fix the cause and run it again.');
            redirect('/admin/system');
        }

        \Techbiss\Core\Cache::flush();

        if ($log === [] && $copy['rows'] === 0) {
            flash('success', 'The database was already up to date.');
            redirect('/admin/system');
        }

        $parts = [];
        if ($log !== []) {
            $parts[] = count($log) . ' database ' . (count($log) === 1 ? 'update' : 'updates');
        }
        if ($copy['rows'] > 0) {
            $parts[] = $copy['rows'] . ' ' . ($copy['rows'] === 1 ? 'piece' : 'pieces')
                . ' of seeded content brought up to date';
        }

        ActivityLog::record('migrate', 'system', null, 'Applied ' . implode(' and ', $parts));
        $this->ok(ucfirst(implode(' and ', $parts)) . ' applied.', '/admin/system');
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
        // Not export.manage: that is held by roles who need the lead and
        // customer CSVs, and this dump is a different thing entirely — it
        // carries every administrator's password hash. A role that cannot
        // open System & maintenance must not be able to download it.
        $this->authorize('system.backup');
        $this->verify($request);
        $db = Database::instance();

        $tables = array_map('strval', $db->column(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
        ));

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="techbiss-backup-' . date('Y-m-d-His') . '.sql"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        echo "-- TECHBISS database export\n-- Generated " . date('c') . "\n";
        echo "--\n";
        echo "-- SENSITIVE. This is a complete backup, so it contains administrator\n";
        echo "-- password hashes and the personal details of every customer and lead.\n";
        echo "-- Store it somewhere private and delete copies you no longer need.\n";
        echo "--\n";
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
            // Login attempts are throttling state, not data worth restoring.
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
