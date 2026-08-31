<?php
declare(strict_types=1);

namespace Techbiss\Core;

use PDO;

/**
 * Bring an installed database up to the current schema.
 *
 * There is no migration history to replay. Instead the desired structure is
 * read from database/schema.sql — the same file a fresh install uses — and
 * compared against what the database actually has. Whatever is missing is
 * added. That means the two paths can never drift: adding a table or a column
 * to schema.sql is all it takes for an existing site to gain it too.
 *
 * Only additive changes are ever made. Nothing is dropped, narrowed, renamed or
 * re-typed, and no content row is touched, so running this on a live site
 * cannot lose data. A column that exists but whose definition has since changed
 * is reported rather than altered, because rewriting a populated column is a
 * decision for a person, not for a script.
 *
 * refreshCopy() is a second, separate operation that does rewrite content — the
 * seeded wording only, and only where it is still exactly as installed. It is
 * never part of apply(); a caller asks for it deliberately.
 */
final class Migrator
{
    private PDO $pdo;
    private string $schemaFile;
    private string $copyFile;

    public function __construct(PDO $pdo, string $schemaFile, ?string $copyFile = null)
    {
        $this->pdo        = $pdo;
        $this->schemaFile = $schemaFile;
        $this->copyFile   = $copyFile ?? dirname($schemaFile) . '/copy-refresh.sql';
    }

    /**
     * Bring seeded copy up to the current wording.
     *
     * seed.sql only runs at install time, so a site set up on an earlier release
     * keeps the text it was installed with — including, at one point, sentences
     * telling visitors to look for prices the site no longer publishes. Every
     * statement in copy-refresh.sql is guarded on the original text, so a
     * sentence the owner has rewritten themselves is never overwritten.
     *
     * A dry run does the same work inside a transaction and rolls it back, so
     * the number it reports is the real one rather than an estimate.
     *
     * @return array{rows:int,statements:int}
     */
    public function refreshCopy(bool $dryRun = false): array
    {
        if (!is_file($this->copyFile)) {
            return ['rows' => 0, 'statements' => 0];
        }

        $statements = array_values(array_filter(
            self::splitScript((string) file_get_contents($this->copyFile)),
            static fn (string $sql): bool => stripos($sql, 'UPDATE') === 0
        ));
        if ($statements === []) {
            return ['rows' => 0, 'statements' => 0];
        }

        $rows = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $rows += (int) $this->pdo->exec($statement);
            }
            if ($dryRun) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['rows' => $rows, 'statements' => count($statements)];
    }

    /**
     * Split a plain SQL script into statements, respecting quoted strings so a
     * semicolon inside a sentence does not cut a statement in half.
     *
     * @return list<string>
     */
    private static function splitScript(string $sql): array
    {
        $out     = [];
        $buf     = '';
        $inQuote = false;
        $len     = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($inQuote) {
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($c === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= $sql[++$i];
                    } else {
                        $inQuote = false;
                    }
                }
                continue;
            }

            if ($c === "'") {
                $inQuote = true;
                $buf    .= $c;
                continue;
            }

            // A comment runs to the end of its line and never holds a statement.
            if ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $buf .= "\n";
                continue;
            }

            if ($c === ';') {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        $stmt = trim($buf);
        if ($stmt !== '') {
            $out[] = $stmt;
        }

        return $out;
    }

    /**
     * What would change, without changing anything.
     *
     * @return array{
     *   tables:array<int,array{name:string,sql:string}>,
     *   columns:array<int,array{table:string,column:string,sql:string}>,
     *   indexes:array<int,array{table:string,name:string,sql:string}>,
     *   data:array<int,array{label:string,sql:string,params:array<int,mixed>}>,
     *   mismatched:array<int,string>
     * }
     */
    public function pending(): array
    {
        $desired  = $this->parseSchema();
        $existing = $this->existingTables();

        $tables = $columns = $indexes = $mismatched = [];

        foreach ($desired as $table => $spec) {
            if (!isset($existing[$table])) {
                $tables[] = ['name' => $table, 'sql' => $spec['create']];
                continue;
            }

            $have = $this->existingColumns($table);
            $prev = null;
            foreach ($spec['columns'] as $name => $definition) {
                if (!isset($have[$name])) {
                    $after = ($prev !== null && isset($have[$prev])) ? ' AFTER `' . $prev . '`' : '';
                    $columns[] = [
                        'table'  => $table,
                        'column' => $name,
                        'sql'    => 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $definition . $after,
                    ];
                } elseif (!$this->definitionMatches($have[$name], $definition)) {
                    $mismatched[] = $table . '.' . $name;
                }
                $prev = $name;
            }

            $haveIndexes = $this->existingIndexes($table);
            foreach ($spec['indexes'] as $name => $definition) {
                if (!isset($haveIndexes[$name])) {
                    $indexes[] = [
                        'table' => $table,
                        'name'  => $name,
                        'sql'   => 'ALTER TABLE `' . $table . '` ADD ' . $definition,
                    ];
                }
            }
        }

        // Report the rows for tables this run would create as well, so a dry run
        // shows the whole picture rather than only what today's schema allows.
        $afterTables = $existing;
        foreach ($tables as $t) {
            $afterTables[$t['name']] = true;
        }

        return [
            'tables'     => $tables,
            'columns'    => $columns,
            'indexes'    => $indexes,
            'data'       => $this->pendingData($afterTables, $existing),
            'mismatched' => $mismatched,
        ];
    }


    /**
     * Apply everything pending. Returns one line per change made.
     *
     * @return array<int,string>
     */
    public function apply(): array
    {
        $pending = $this->pending();
        $log     = [];

        foreach ($pending['tables'] as $t) {
            $this->pdo->exec($t['sql']);
            $log[] = 'Created table ' . $t['name'];
        }
        foreach ($pending['columns'] as $c) {
            $this->pdo->exec($c['sql']);
            $log[] = 'Added ' . $c['table'] . '.' . $c['column'];
        }
        foreach ($pending['indexes'] as $i) {
            try {
                $this->pdo->exec($i['sql']);
                $log[] = 'Added index ' . $i['name'] . ' on ' . $i['table'];
            } catch (\PDOException $e) {
                // A foreign key implies its own index, so some are already
                // satisfied under another name. Not a failure.
                $log[] = 'Skipped index ' . $i['name'] . ' on ' . $i['table'] . ' (already satisfied)';
            }
        }
        // Recomputed after the structural work: rows that belong in a table
        // this same run has just created were not offered the first time,
        // because the table did not exist when pending() looked.
        foreach ($this->pendingData($this->existingTables()) as $d) {
            $stmt = $this->pdo->prepare($d['sql']);
            $stmt->execute($d['params']);
            if ($stmt->rowCount() > 0) {
                $log[] = $d['label'];
            }
        }

        return $log;
    }

    // -----------------------------------------------------------------
    // Reading the desired structure out of schema.sql
    // -----------------------------------------------------------------

    /**
     * @return array<string,array{create:string,columns:array<string,string>,indexes:array<string,string>}>
     */
    private function parseSchema(): array
    {
        if (!is_file($this->schemaFile)) {
            throw new \RuntimeException('schema.sql is missing, so the database cannot be checked.');
        }
        $sql = (string) file_get_contents($this->schemaFile);

        // Strip line comments first. A "-- note" sitting above a column leaves
        // that column glued to the comment once the body is split on commas,
        // and the column pattern then fails to match — silently dropping it
        // from everything below.
        $sql = preg_replace('/^[ \t]*--[^\n]*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/\s--[^\n]*$/m', '', $sql) ?? $sql;

        $tables = [];
        $re = '/CREATE TABLE IF NOT EXISTS\s+`([a-z0-9_]+)`\s*\((.*?)\)\s*ENGINE=[^;]*;/is';
        if (!preg_match_all($re, $sql, $matches, PREG_SET_ORDER)) {
            return $tables;
        }

        foreach ($matches as $m) {
            $table = $m[1];
            $body  = $m[2];

            $columns = [];
            $indexes = [];
            foreach ($this->splitDefinitions($body) as $line) {
                if (preg_match('/^`([a-z0-9_]+)`\s+(.+)$/is', $line, $c)) {
                    $columns[$c[1]] = '`' . $c[1] . '` ' . trim($c[2]);
                    continue;
                }
                // Named keys only: PRIMARY KEY and CONSTRAINT are left to the
                // CREATE for a new table, and never bolted onto an old one.
                if (preg_match('/^(UNIQUE\s+KEY|KEY)\s+`([a-z0-9_]+)`\s*(\(.+\))$/is', $line, $k)) {
                    $indexes[$k[2]] = strtoupper(trim($k[1])) . ' `' . $k[2] . '` ' . trim($k[3]);
                }
            }

            $tables[$table] = [
                'create'  => rtrim($m[0], ';'),
                'columns' => $columns,
                'indexes' => $indexes,
            ];
        }

        return $tables;
    }

    /**
     * Split a CREATE TABLE body on commas that are not inside brackets or quotes.
     *
     * @return array<int,string>
     */
    private function splitDefinitions(string $body): array
    {
        $out   = [];
        $depth = 0;
        $buf   = '';
        $quote = '';
        $len   = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];

            if ($quote !== '') {
                $buf .= $ch;
                if ($ch === $quote && ($i === 0 || $body[$i - 1] !== '\\')) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $buf  .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $out[] = trim($buf);
                $buf   = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return array_values(array_filter($out, static fn (string $l): bool => $l !== ''));
    }

    // -----------------------------------------------------------------
    // Reading what the database actually has
    // -----------------------------------------------------------------

    /** @return array<string,true> */
    private function existingTables(): array
    {
        $rows = $this->pdo->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchAll(PDO::FETCH_COLUMN);

        $out = [];
        foreach ($rows as $name) {
            $out[(string) $name] = true;
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    private function existingColumns(string $table): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT column_name, column_type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['column_name']] = $row;
        }
        return $out;
    }

    /** @return array<string,true> */
    private function existingIndexes(string $table): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT index_name FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $out[(string) $name] = true;
        }
        return $out;
    }

    /**
     * Does the live column still match what schema.sql asks for?
     *
     * Compared loosely — only the base type and nullability — because MySQL and
     * MariaDB report widths and defaults differently enough that a strict
     * comparison would cry wolf on every install.
     */
    private function definitionMatches(array $live, string $desired): bool
    {
        $wanted = strtolower($desired);
        $type   = strtolower((string) $live['column_type']);

        if (!preg_match('/^`[a-z0-9_]+`\s+([a-z]+)/i', $wanted, $m)) {
            return true;
        }
        $wantedBase = $m[1];
        $liveBase   = preg_match('/^([a-z]+)/', $type, $t) ? $t[1] : $type;

        if ($wantedBase !== $liveBase) {
            return false;
        }
        $wantsNotNull = str_contains($wanted, 'not null');
        $isNotNull    = strtoupper((string) $live['is_nullable']) === 'NO';

        return $wantsNotNull === $isNotNull;
    }

    // -----------------------------------------------------------------
    // System rows that a new feature needs in order to work
    // -----------------------------------------------------------------

    /**
     * Rows the application depends on — permissions, taxonomy and navigation
     * entries introduced with a new feature. Content is never seeded here: a
     * service or package removed in the admin stays removed.
     *
     * Each statement is idempotent in its own right, but every one also carries
     * a check so that a row already present is not reported as pending. Without
     * that, a dry run would list work that applying would not actually do.
     *
     * @param array<string,true> $existing tables that exist, or will by the time this runs
     * @param array<string,true>|null $queryable tables safe to query now; null means all of $existing
     * @return array<int,array{label:string,sql:string,params:array<int,mixed>}>
     */
    private function pendingData(array $existing, ?array $queryable = null): array
    {
        $queryable ??= $existing;

        // A row is needed unless we can query for it and find it already there.
        $needed = function (string $table, string $where, array $params) use ($queryable): bool {
            if (!isset($queryable[$table])) {
                return true; // table arrives with this run, so nothing can be in it yet
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM `' . $table . '` ' . $where . ' LIMIT 1');
            $stmt->execute($params);
            return $stmt->fetchColumn() === false;
        };

        $out = [];

        $permissions = [
            ['Manage premade projects',  'projects.manage',       'Commerce', 33],
            ['Manage project enquiries', 'project_orders.manage', 'Commerce', 34],
            ['Download a full database backup', 'system.backup', 'System', 54],
        ];
        if (isset($existing['permissions'])) {
            foreach ($permissions as [$name, $slug, $group, $order]) {
                if (!$needed('permissions', 'WHERE slug = ?', [$slug])) {
                    continue;
                }
                $out[] = [
                    'label'  => 'Added the "' . $name . '" permission',
                    'sql'    => 'INSERT IGNORE INTO `permissions` (`name`,`slug`,`group_name`,`sort_order`) VALUES (?,?,?,?)',
                    'params' => [$name, $slug, $group, $order],
                ];
            }
        }

        // A new permission is useless until a role holds it. Super Admin gets
        // everything by definition; the others get only what suits them.
        if (isset($existing['role_permissions'], $existing['roles'], $existing['permissions'])) {
            $grants = [
                'super-admin'     => ['projects.manage', 'project_orders.manage', 'system.backup'],
                'content-manager' => ['projects.manage'],
                'sales-manager'   => ['project_orders.manage'],
                'support-manager' => ['project_orders.manage'],
            ];
            foreach ($grants as $role => $slugs) {
                foreach ($slugs as $slug) {
                    $held = $needed(
                        'role_permissions',
                        'rp JOIN roles r ON r.id = rp.role_id
                         JOIN permissions p ON p.id = rp.permission_id
                         WHERE r.slug = ? AND p.slug = ?',
                        [$role, $slug]
                    );
                    if (!$held) {
                        continue;
                    }
                    $out[] = [
                        'label'  => 'Granted ' . $slug . ' to ' . $role,
                        'sql'    => 'INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
                                     SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.slug = ?
                                     WHERE r.slug = ?',
                        'params' => [$slug, $role],
                    ];
                }
            }
        }

        // Settings are read from the database, so a key that only exists in
        // seed.sql is invisible on an upgraded site — the toggle it controls
        // can never be reached. Values are only ever inserted, never updated,
        // so a setting already tuned in the admin is left alone.
        $settings = [
            ['commerce', 'public_pricing', '0', 'bool', 'Show prices on the website',
             'Off by default: packages and services are priced in conversation. Turn on only if you want figures published.', 0],
        ];
        if (isset($existing['settings'])) {
            foreach ($settings as [$group, $key, $value, $type, $label, $hint, $order]) {
                if (!$needed('settings', 'WHERE key_name = ?', [$key])) {
                    continue;
                }
                $out[] = [
                    'label'  => 'Added the "' . $label . '" setting',
                    'sql'    => 'INSERT IGNORE INTO `settings`
                                 (`group_name`,`key_name`,`value`,`type`,`label`,`hint`,`sort_order`,`updated_at`)
                                 VALUES (?,?,?,?,?,?,?,NOW())',
                    'params' => [$group, $key, $value, $type, $label, $hint, $order],
                ];
            }
        }

        if (isset($existing['project_categories'])) {
            $categories = [
                ['business-website', 'Business Website',       'Company sites ready to brand and launch.', 'globe',     1],
                ['online-store',     'Online Store',           'Product catalogues, cart and checkout.',   'cart',      2],
                ['booking',          'Booking & Appointments', 'Calendars, slots and reminders.',          'calendar',  3],
                ['portfolio-site',   'Portfolio & Personal',   'Single-person and studio sites.',          'image',     4],
                ['restaurant',       'Restaurant & Cafe',      'Menus, orders and table booking.',         'utensils',  5],
                ['directory',        'Directory & Listings',   'Searchable listings with profiles.',       'list',      6],
                ['dashboard',        'Admin & Dashboard',      'Internal tools and back offices.',         'dashboard', 7],
                ['landing',          'Landing Page',           'One-page sites built to convert.',         'rocket',    8],
            ];
            foreach ($categories as [$slug, $name, $description, $icon, $order]) {
                if (!$needed('project_categories', 'WHERE slug = ?', [$slug])) {
                    continue;
                }
                $out[] = [
                    'label'  => 'Added the "' . $name . '" project category',
                    'sql'    => 'INSERT IGNORE INTO `project_categories`
                                 (`slug`,`name`,`description`,`icon`,`is_published`,`sort_order`,`created_at`,`updated_at`)
                                 VALUES (?,?,?,?,1,?,NOW(),NOW())',
                    'params' => [$slug, $name, $description, $icon, $order],
                ];
            }
        }

        // Navigation has no unique key, so guard on the URL not already being
        // there — an entry the owner deleted on purpose stays deleted only if
        // they also renamed it, which is the best a script can honestly do.
        if (isset($existing['navigation'])) {
            $links = [
                ['primary', 'Ready Projects', '/premade-projects', 'Live builds you can launch fast', 3],
                ['footer',  'Ready Projects', '/premade-projects', '', 3],
            ];
            foreach ($links as [$menu, $label, $url, $description, $order]) {
                if (!$needed('navigation', 'WHERE menu = ? AND url = ?', [$menu, $url])) {
                    continue;
                }
                $out[] = [
                    'label'  => 'Added "' . $label . '" to the ' . $menu . ' menu',
                    'sql'    => 'INSERT INTO `navigation`
                                 (`menu`,`parent_id`,`label`,`url`,`link_type`,`description`,`target`,`is_active`,`is_button`,`sort_order`,`created_at`,`updated_at`)
                                 SELECT ?, NULL, ?, ?, \'internal\', ?, \'_self\', 1, 0, ?, NOW(), NOW()
                                 FROM DUAL
                                 WHERE NOT EXISTS (SELECT 1 FROM `navigation` n WHERE n.menu = ? AND n.url = ?)',
                    'params' => [$menu, $label, $url, $description, $order, $menu, $url],
                ];
            }
        }

        return $out;
    }
}
