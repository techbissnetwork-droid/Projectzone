<?php
declare(strict_types=1);

namespace App\Core;

use PDOException;
use Throwable;

/**
 * The Advanced Installer engine.
 *
 * Shared by the web wizard and the CLI installer. Responsible for:
 *   - environment and requirement verification
 *   - automatic URL detection (proxy, sub-directory and HTTPS aware)
 *   - existing-site and existing-database detection
 *   - clean install, upgrade-in-place and migrate-from-other-platform modes
 *   - content import with old → new URL rewriting
 *   - writing the runtime configuration and locking the installer
 */
final class Installer
{
    public const STEPS = [
        'requirements' => ['label' => 'Requirements', 'blurb' => 'Verify the runtime can host the platform.'],
        'environment' => ['label' => 'Environment', 'blurb' => 'Confirm the URL we detected for this installation.'],
        'database' => ['label' => 'Database', 'blurb' => 'Connect the platform to its datastore.'],
        'detection' => ['label' => 'Existing site', 'blurb' => 'Scan the target for anything already installed.'],
        'migration' => ['label' => 'Migration', 'blurb' => 'Import content and rewrite URLs from a previous platform.'],
        'configuration' => ['label' => 'Configuration', 'blurb' => 'Name the site and create the owner account.'],
        'install' => ['label' => 'Install', 'blurb' => 'Apply the schema and write the configuration.'],
        'deploy' => ['label' => 'Deploy', 'blurb' => 'Post-install checklist and server configuration.'],
    ];

    /** @var list<string> */
    private array $log = [];

    public function __construct(private Application $app)
    {
    }

    public function log(): array
    {
        return $this->log;
    }

    private function note(string $level, string $message): void
    {
        $this->log[] = $level . '|' . $message;
    }

    /* -------------------------------------------------------------------- */
    /* Requirements                                                          */
    /* -------------------------------------------------------------------- */

    /**
     * @return list<array{key:string,label:string,value:string,status:string,required:bool,hint:string}>
     */
    public function requirements(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $checks[] = [
            'key' => 'php',
            'label' => 'PHP version',
            'value' => PHP_VERSION,
            'status' => $phpOk ? 'pass' : 'fail',
            'required' => true,
            'hint' => 'PHP 8.1 or newer is required. 8.2+ is recommended.',
        ];

        foreach ([
            'pdo' => 'Database abstraction layer',
            'mbstring' => 'Multibyte string handling',
            'json' => 'JSON encoding',
            'openssl' => 'Secure token generation',
        ] as $extension => $why) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'key' => 'ext_' . $extension,
                'label' => 'Extension: ' . $extension,
                'value' => $loaded ? 'Loaded' : 'Missing',
                'status' => $loaded ? 'pass' : 'fail',
                'required' => true,
                'hint' => $why,
            ];
        }

        $drivers = \PDO::getAvailableDrivers();
        $hasSqlite = in_array('sqlite', $drivers, true);
        $hasMysql = in_array('mysql', $drivers, true);
        $checks[] = [
            'key' => 'pdo_drivers',
            'label' => 'Database drivers',
            'value' => $drivers === [] ? 'None' : implode(', ', $drivers),
            'status' => ($hasSqlite || $hasMysql) ? 'pass' : 'fail',
            'required' => true,
            'hint' => 'At least one of pdo_sqlite or pdo_mysql must be available.',
        ];

        foreach ([
            'zlib' => ['Compression', false],
            'intl' => ['Locale formatting', false],
            'curl' => ['Outbound HTTP', false],
        ] as $extension => [$why, $required]) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'key' => 'ext_' . $extension,
                'label' => 'Extension: ' . $extension,
                'value' => $loaded ? 'Loaded' : 'Not loaded',
                'status' => $loaded ? 'pass' : 'warn',
                'required' => $required,
                'hint' => $why . ' — optional, the platform runs without it.',
            ];
        }

        foreach (['storage', 'storage/db', 'storage/cache', 'storage/logs', 'storage/uploads'] as $path) {
            $full = $this->app->path($path);
            if (!is_dir($full)) {
                @mkdir($full, 0775, true);
            }
            $writable = is_dir($full) && is_writable($full);
            $checks[] = [
                'key' => 'write_' . str_replace('/', '_', $path),
                'label' => 'Writable: ' . $path,
                'value' => $writable ? 'Writable' : 'Not writable',
                'status' => $writable ? 'pass' : 'fail',
                'required' => true,
                'hint' => 'The web server user must be able to write to ' . $path . '.',
            ];
        }

        $memory = ini_get('memory_limit');
        $memoryBytes = self::toBytes((string) $memory);
        $checks[] = [
            'key' => 'memory',
            'label' => 'Memory limit',
            'value' => (string) $memory,
            'status' => ($memoryBytes === -1 || $memoryBytes >= 64 * 1024 * 1024) ? 'pass' : 'warn',
            'required' => false,
            'hint' => '64M is comfortable; the platform typically peaks near 12M.',
        ];

        return $checks;
    }

    public function requirementsSatisfied(): bool
    {
        foreach ($this->requirements() as $check) {
            if ($check['required'] && $check['status'] === 'fail') {
                return false;
            }
        }
        return true;
    }

    private static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /* -------------------------------------------------------------------- */
    /* Environment / URL detection                                           */
    /* -------------------------------------------------------------------- */

    /**
     * Everything the installer could work out about where this site lives.
     *
     * @return array<string,string|bool>
     */
    public function detectEnvironment(?array $server = null): array
    {
        $server = $server ?? $_SERVER;

        $scheme = Request::detectScheme($server);
        $host = Request::detectHost($server);
        $basePath = Request::detectBasePath($server);
        $documentRoot = rtrim(str_replace('\\', '/', (string) ($server['DOCUMENT_ROOT'] ?? '')), '/');
        $publicPath = $this->app->path('public');

        $behindProxy = isset($server['HTTP_X_FORWARDED_PROTO'])
            || isset($server['HTTP_X_FORWARDED_HOST'])
            || isset($server['HTTP_CF_CONNECTING_IP'])
            || isset($server['HTTP_X_FORWARDED_FOR']);

        return [
            'url' => $scheme . '://' . $host . $basePath,
            'scheme' => $scheme,
            'host' => $host,
            'base_path' => $basePath,
            'in_subdirectory' => $basePath !== '',
            'https' => $scheme === 'https',
            'behind_proxy' => $behindProxy,
            'document_root' => $documentRoot,
            'public_path' => $publicPath,
            'docroot_is_public' => $documentRoot !== '' && rtrim($documentRoot, '/') === rtrim($publicPath, '/'),
            'server_software' => (string) ($server['SERVER_SOFTWARE'] ?? PHP_SAPI),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
        ];
    }

    /* -------------------------------------------------------------------- */
    /* Database                                                              */
    /* -------------------------------------------------------------------- */

    /**
     * @return array{ok:bool,message:string,server?:string,tables?:int}
     */
    public function testConnection(array $config): array
    {
        try {
            $pdo = Database::connect($config);
            $driver = strtolower((string) ($config['driver'] ?? 'sqlite'));

            if ($driver === 'sqlite') {
                $path = (string) $config['database'];
                $writable = $path === ':memory:' || is_writable(is_file($path) ? $path : dirname($path));
                if (!$writable) {
                    return ['ok' => false, 'message' => 'The SQLite file location is not writable: ' . dirname($path)];
                }
                $version = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
                $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
                return ['ok' => true, 'message' => 'SQLite ' . $version, 'server' => 'SQLite ' . $version, 'tables' => $tables];
            }

            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            return ['ok' => true, 'message' => 'MySQL ' . $version, 'server' => $version, 'tables' => $tables];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => self::friendlyDbError($e)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private static function friendlyDbError(PDOException $e): string
    {
        $message = $e->getMessage();
        return match (true) {
            str_contains($message, 'Access denied') => 'Access denied — check the username and password.',
            str_contains($message, 'Unknown database') => 'That database does not exist. Create it first, or correct the name.',
            str_contains($message, 'Connection refused') => 'Connection refused — check the host and port, and that the server is running.',
            str_contains($message, 'getaddrinfo') || str_contains($message, 'Name or service not known') => 'That host could not be resolved.',
            str_contains($message, 'unable to open database') => 'The SQLite file could not be opened. Check the path and directory permissions.',
            str_contains($message, 'timed out') => 'The connection timed out. Check firewall rules and that remote access is permitted.',
            default => trim(preg_replace('/\s+/', ' ', $message) ?? $message),
        };
    }

    /* -------------------------------------------------------------------- */
    /* Existing-site detection                                               */
    /* -------------------------------------------------------------------- */

    /**
     * Look for anything already installed at the target: a previous TECHBISS
     * install, another CMS, or a populated database. This is what lets the
     * installer offer "upgrade" or "migrate" instead of silently overwriting.
     *
     * @return array{found:bool,platform:?string,confidence:string,signals:list<array{label:string,detail:string}>,recommended_mode:string,database:array}
     */
    public function scanExisting(array $dbConfig = []): array
    {
        $signals = [];
        $platform = null;
        $root = dirname($this->app->path('public'));
        $parent = dirname($root);

        // A previous TECHBISS installation.
        if (is_file($this->app->path('storage/installed.php'))) {
            $platform = 'techbiss';
            $existing = @include $this->app->path('storage/installed.php');
            $version = is_array($existing) ? (string) ($existing['app']['version'] ?? 'unknown') : 'unknown';
            $signals[] = ['label' => 'TECHBISS installation found', 'detail' => 'Configuration present, version ' . $version];
        }

        // Other platforms, checked in the application root and one level up so
        // an install into a sub-directory of an existing site is detected.
        $fingerprints = [
            'wordpress' => [['wp-config.php', 'wp-load.php', 'wp-content'], 'WordPress'],
            'joomla' => [['configuration.php', 'administrator/manifests'], 'Joomla'],
            'drupal' => [['sites/default/settings.php', 'core/lib/Drupal.php'], 'Drupal'],
            'laravel' => [['artisan', 'bootstrap/app.php'], 'Laravel'],
            'magento' => [['bin/magento', 'app/etc/env.php'], 'Magento'],
            'static' => [['index.html'], 'Static HTML site'],
        ];

        foreach ($fingerprints as $key => [$paths, $label]) {
            foreach ([$root, $parent, $this->app->path('public')] as $base) {
                $hits = 0;
                foreach ($paths as $path) {
                    if (file_exists($base . '/' . $path)) {
                        $hits++;
                    }
                }
                if ($hits > 0 && $hits === count($paths)) {
                    $platform ??= $key;
                    $signals[] = ['label' => $label . ' detected', 'detail' => 'Found in ' . $base];
                    break;
                }
            }
        }

        // A populated database is the strongest signal of all.
        $database = ['reachable' => false, 'tables' => 0, 'platform_tables' => 0, 'foreign_tables' => []];
        if ($dbConfig !== []) {
            try {
                $db = new Database($dbConfig);
                $tables = $db->tables();
                $database['reachable'] = true;
                $database['tables'] = count($tables);

                $platformTables = ['users', 'products', 'orders', 'licenses', 'deployments', 'settings'];
                $ours = array_values(array_intersect($tables, $platformTables));
                $database['platform_tables'] = count($ours);

                $foreign = [];
                foreach ($tables as $table) {
                    if (str_starts_with($table, 'wp_')) {
                        $foreign['wordpress'] = true;
                    } elseif (str_starts_with($table, 'jos_') || str_starts_with($table, '#__')) {
                        $foreign['joomla'] = true;
                    } elseif ($table === 'node_field_data' || $table === 'watchdog') {
                        $foreign['drupal'] = true;
                    }
                }
                $database['foreign_tables'] = array_keys($foreign);

                if (count($ours) >= 4) {
                    $platform = 'techbiss';
                    $rows = (int) $db->value('SELECT COUNT(*) FROM users', [], 0);
                    $signals[] = ['label' => 'Existing platform schema', 'detail' => count($ours) . ' platform tables, ' . $rows . ' user records'];
                } elseif ($foreign !== []) {
                    $platform ??= array_key_first($foreign);
                    $signals[] = ['label' => 'Foreign schema in database', 'detail' => ucfirst((string) array_key_first($foreign)) . ' tables present alongside ' . count($tables) . ' total tables'];
                } elseif ($tables !== []) {
                    $signals[] = ['label' => 'Database is not empty', 'detail' => count($tables) . ' existing tables found'];
                }
            } catch (Throwable $e) {
                $database['error'] = self::friendlyDbError(new PDOException($e->getMessage()));
            }
        }

        $found = $signals !== [];
        $confidence = match (true) {
            count($signals) >= 2 => 'high',
            count($signals) === 1 => 'medium',
            default => 'none',
        };

        $recommended = match (true) {
            $platform === 'techbiss' => 'upgrade',
            $platform !== null => 'migrate',
            default => 'clean',
        };

        return [
            'found' => $found,
            'platform' => $platform,
            'confidence' => $confidence,
            'signals' => $signals,
            'recommended_mode' => $recommended,
            'database' => $database,
        ];
    }

    /* -------------------------------------------------------------------- */
    /* Migration / import                                                    */
    /* -------------------------------------------------------------------- */

    /**
     * Import content from an export file, rewriting absolute URLs from the old
     * origin to the new one. Accepts the platform's own JSON export format and
     * a generic CSV of pages.
     *
     * @return array{ok:bool,imported:int,rewritten:int,messages:list<string>}
     */
    public function importContent(Database $db, string $file, string $oldUrl, string $newUrl): array
    {
        $messages = [];
        $imported = 0;
        $rewritten = 0;

        if (!is_file($file) || !is_readable($file)) {
            return ['ok' => false, 'imported' => 0, 'rewritten' => 0, 'messages' => ['Import file could not be read.']];
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $records = [];

        if ($extension === 'json') {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'imported' => 0, 'rewritten' => 0, 'messages' => ['The JSON export could not be parsed.']];
            }
            $records = $decoded['resources'] ?? $decoded['posts'] ?? $decoded['items'] ?? $decoded;
        } elseif ($extension === 'csv') {
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                return ['ok' => false, 'imported' => 0, 'rewritten' => 0, 'messages' => ['The CSV export could not be opened.']];
            }
            $header = fgetcsv($handle);
            while ($header !== false && ($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($header)) {
                    $records[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        } else {
            return ['ok' => false, 'imported' => 0, 'rewritten' => 0, 'messages' => ['Unsupported import format. Use JSON or CSV.']];
        }

        if (!is_array($records)) {
            $records = [];
        }

        $oldUrl = rtrim($oldUrl, '/');
        $newUrl = rtrim($newUrl, '/');

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $title = trim((string) ($record['title'] ?? $record['post_title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $body = (string) ($record['body'] ?? $record['content'] ?? $record['post_content'] ?? '');
            $excerpt = (string) ($record['excerpt'] ?? $record['summary'] ?? '');

            if ($oldUrl !== '' && $oldUrl !== $newUrl) {
                $count = 0;
                $body = str_replace($oldUrl, $newUrl, $body, $count);
                $rewritten += $count;
                $excerpt = str_replace($oldUrl, $newUrl, $excerpt);
            }

            $slug = str_slug((string) ($record['slug'] ?? $record['post_name'] ?? $title));
            if ($slug === '') {
                continue;
            }
            if ((int) $db->value('SELECT COUNT(*) FROM resources WHERE slug = ?', [$slug], 0) > 0) {
                $slug .= '-' . substr(hash('xxh128', $title), 0, 5);
            }

            $db->insert('resources', [
                'slug' => $slug,
                'title' => mb_substr($title, 0, 190),
                'excerpt' => $excerpt !== '' ? Seo::clamp($excerpt, 220) : Seo::clamp($body, 220),
                'body' => $body,
                'type' => 'Article',
                'topic' => (string) ($record['topic'] ?? $record['category'] ?? 'Imported'),
                'author' => (string) ($record['author'] ?? 'Imported'),
                'author_role' => null,
                'read_minutes' => max(2, (int) ceil(str_word_count(strip_tags($body)) / 220)),
                'accent' => 'blue',
                'featured' => 0,
                'published_at' => (string) ($record['published_at'] ?? $record['post_date'] ?? gmdate('Y-m-d')),
                'created_at' => gmdate('c'),
            ]);
            $imported++;
        }

        $messages[] = "Imported {$imported} content records.";
        if ($rewritten > 0) {
            $messages[] = "Rewrote {$rewritten} absolute URLs from {$oldUrl} to {$newUrl}.";
        }

        return ['ok' => true, 'imported' => $imported, 'rewritten' => $rewritten, 'messages' => $messages];
    }

    /* -------------------------------------------------------------------- */
    /* Install                                                               */
    /* -------------------------------------------------------------------- */

    /**
     * @return array{ok:bool,log:list<string>}
     */
    public function install(array $payload): array
    {
        $this->log = [];
        $mode = (string) ($payload['mode'] ?? 'clean');
        $dbConfig = (array) ($payload['database'] ?? []);

        $this->note('ok', 'Starting ' . $mode . ' installation');

        $test = $this->testConnection($dbConfig);
        if (!$test['ok']) {
            $this->note('err', 'Database connection failed: ' . $test['message']);
            return ['ok' => false, 'log' => $this->log];
        }
        $this->note('ok', 'Connected to ' . ($test['server'] ?? 'database'));

        $db = new Database($dbConfig);

        $migrator = new Migrator($db, $this->app->basePath);
        if (!$migrator->run()) {
            foreach ($migrator->log() as $line) {
                $this->log[] = $line;
            }
            $this->note('err', 'Schema could not be applied');
            return ['ok' => false, 'log' => $this->log];
        }
        foreach ($migrator->log() as $line) {
            $this->log[] = $line;
        }

        $adminEmail = strtolower(trim((string) ($payload['admin_email'] ?? '')));
        $isUpgrade = $mode === 'upgrade';
        $hasUsers = !$migrator->isEmpty();

        if ($isUpgrade && $hasUsers) {
            $this->note('ok', 'Upgrade mode — existing records preserved');
        } else {
            if ($hasUsers) {
                $this->note('warn', 'Existing user records found; the owner account was not recreated');
            } else {
                $seeder = new Seeder($db, $this->app->basePath);
                $seeder->run([
                    'name' => (string) ($payload['admin_name'] ?? 'Platform Owner'),
                    'email' => $adminEmail !== '' ? $adminEmail : 'admin@techbiss.com',
                    'password' => (string) ($payload['admin_password'] ?? bin2hex(random_bytes(6))),
                ], (bool) ($payload['demo_data'] ?? true));
                foreach ($seeder->log() as $line) {
                    $this->log[] = $line;
                }
            }
        }

        // Optional content import.
        $importFile = (string) ($payload['import_file'] ?? '');
        if ($importFile !== '' && is_file($importFile)) {
            $result = $this->importContent(
                $db,
                $importFile,
                (string) ($payload['old_url'] ?? ''),
                (string) ($payload['url'] ?? '')
            );
            foreach ($result['messages'] as $message) {
                $this->note($result['ok'] ? 'ok' : 'err', $message);
            }
        }

        if (!$this->writeConfig($payload, $dbConfig)) {
            $this->note('err', 'Could not write storage/installed.php — check directory permissions');
            return ['ok' => false, 'log' => $this->log];
        }
        $this->note('ok', 'Wrote runtime configuration');

        if (!$this->lock()) {
            $this->note('warn', 'Installed, but the lock file could not be written');
        } else {
            $this->note('ok', 'Installer locked');
        }

        @unlink($this->app->path('storage/install.unlock'));
        $this->app->make('cache')->flush();
        $this->note('ok', 'Installation complete');

        return ['ok' => true, 'log' => $this->log];
    }

    private function writeConfig(array $payload, array $dbConfig): bool
    {
        $driver = strtolower((string) ($dbConfig['driver'] ?? 'sqlite'));
        $detectUrl = (bool) ($payload['detect_url'] ?? true);

        $config = [
            'app' => [
                'name' => (string) ($payload['site_name'] ?? 'TECHBISS'),
                'url' => rtrim((string) ($payload['url'] ?? 'http://localhost'), '/'),
                'detect_url' => $detectUrl,
                'timezone' => (string) ($payload['timezone'] ?? 'UTC'),
                'env' => (string) ($payload['env'] ?? 'production'),
                'debug' => (bool) ($payload['debug'] ?? false),
                'version' => Application::VERSION,
                'installed_at' => gmdate('c'),
            ],
            'database' => [
                'default' => $driver === 'sqlite' ? 'sqlite' : 'mysql',
                'connections' => [
                    $driver === 'sqlite' ? 'sqlite' : 'mysql' => $dbConfig,
                ],
            ],
            'session' => [
                'secure' => str_starts_with((string) ($payload['url'] ?? ''), 'https://'),
            ],
        ];

        $export = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "/**\n"
            . " * Written by the TECHBISS Advanced Installer. Values here override\n"
            . " * config/*.php at runtime. Safe to edit; keep it out of version control.\n"
            . " */\n\n"
            . 'return ' . var_export($config, true) . ";\n";

        $path = $this->app->path('storage/installed.php');
        $temp = $path . '.tmp';
        if (@file_put_contents($temp, $export, LOCK_EX) === false) {
            return false;
        }
        return @rename($temp, $path);
    }

    public function lock(): bool
    {
        return @file_put_contents(
            $this->app->path('storage/install.lock'),
            gmdate('c') . ' — installed by TECHBISS Advanced Installer ' . Application::VERSION . "\n"
        ) !== false;
    }

    /** Server configuration snippets shown on the final step. */
    public function deploymentSnippets(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'example.com';

        return [
            'apache' => "# .htaccess is included in public/. Ensure AllowOverride All is set,\n"
                . "# or paste this into your vhost:\n"
                . "<VirtualHost *:443>\n"
                . "    ServerName {$host}\n"
                . "    DocumentRoot /var/www/techbiss/public\n"
                . "    <Directory /var/www/techbiss/public>\n"
                . "        AllowOverride All\n"
                . "        Require all granted\n"
                . "    </Directory>\n"
                . "</VirtualHost>",
            'nginx' => "server {\n"
                . "    listen 443 ssl http2;\n"
                . "    server_name {$host};\n"
                . "    root /var/www/techbiss/public;\n"
                . "    index index.php;\n\n"
                . "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }\n\n"
                . "    location ~ \\.php\$ {\n"
                . "        include fastcgi_params;\n"
                . "        fastcgi_pass unix:/run/php/php8.2-fpm.sock;\n"
                . "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n"
                . "    }\n\n"
                . "    location ~* \\.(css|js|svg|woff2|webp|avif)\$ {\n"
                . "        expires 1y;\n"
                . "        add_header Cache-Control \"public, immutable\";\n"
                . "    }\n"
                . "}",
            'cron' => "# Flush expired cache entries nightly\n"
                . "0 3 * * * cd /var/www/techbiss && php bin/techbiss cache:clear >/dev/null 2>&1",
        ];
    }
}
