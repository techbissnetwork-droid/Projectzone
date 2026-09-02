<?php
/**
 * The database schema, described once and used three ways: to create the
 * tables on a fresh install, to add anything missing on an existing database,
 * and to report what version the database is on.
 *
 * The same definitions run on MySQL and SQLite, so column types stay to the
 * common ground: INTEGER / VARCHAR / TEXT / DECIMAL / DATE / DATETIME.
 *
 * @id and @fk are placeholders swapped for the right thing per driver.
 */

/** Bump this whenever schema_tables() changes. Migration compares against it. */
const SCHEMA_VERSION = 2;

function schema_tables(): array
{
    return [

        'users' => ['columns' => [
            'id'                   => '@id',
            'name'                 => "VARCHAR(160) NOT NULL DEFAULT ''",
            'email'                => "VARCHAR(190) NOT NULL DEFAULT ''",
            'password_hash'        => "VARCHAR(255) NOT NULL DEFAULT ''",
            'role'                 => "VARCHAR(20) NOT NULL DEFAULT 'client'",
            'phone'                => "VARCHAR(60) NOT NULL DEFAULT ''",
            'company'              => "VARCHAR(160) NOT NULL DEFAULT ''",
            'status'               => "VARCHAR(20) NOT NULL DEFAULT 'active'",
            'must_change_password' => 'INTEGER NOT NULL DEFAULT 0',
            'last_login_at'        => 'DATETIME NULL',
            'created_at'           => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_users_email' => ['unique' => true, 'columns' => 'email'],
        ]],

        'settings' => ['columns' => [
            'id'          => '@id',
            'group_name'  => "VARCHAR(60) NOT NULL DEFAULT 'global'",
            'setting_key' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'label'       => "VARCHAR(190) NOT NULL DEFAULT ''",
            'value'       => 'TEXT NULL',
            'field_type'  => "VARCHAR(20) NOT NULL DEFAULT 'text'",
            'sort'        => 'INTEGER NOT NULL DEFAULT 0',
        ], 'indexes' => [
            'idx_settings_key' => ['unique' => true, 'columns' => 'setting_key'],
        ]],

        'services' => ['columns' => [
            'id'        => '@id',
            'slug'      => "VARCHAR(160) NOT NULL DEFAULT ''",
            'title'     => "VARCHAR(190) NOT NULL DEFAULT ''",
            'subtitle'  => "VARCHAR(190) NOT NULL DEFAULT ''",
            'icon'      => "VARCHAR(20) NOT NULL DEFAULT ''",
            'body'      => 'TEXT NULL',
            'bullets'   => 'TEXT NULL',
            'sort'      => 'INTEGER NOT NULL DEFAULT 0',
            'is_active' => 'INTEGER NOT NULL DEFAULT 1',
        ]],

        'industries' => ['columns' => [
            'id'        => '@id',
            'slug'      => "VARCHAR(160) NOT NULL DEFAULT ''",
            'title'     => "VARCHAR(190) NOT NULL DEFAULT ''",
            'icon'      => "VARCHAR(20) NOT NULL DEFAULT ''",
            'body'      => 'TEXT NULL',
            'bullets'   => 'TEXT NULL',
            'sort'      => 'INTEGER NOT NULL DEFAULT 0',
            'is_active' => 'INTEGER NOT NULL DEFAULT 1',
        ]],

        'packages' => ['columns' => [
            'id'          => '@id',
            'name'        => "VARCHAR(120) NOT NULL DEFAULT ''",
            'kind'        => "VARCHAR(20) NOT NULL DEFAULT 'build'",
            'price'       => "VARCHAR(40) NOT NULL DEFAULT ''",
            'period'      => "VARCHAR(40) NOT NULL DEFAULT 'One-off'",
            'blurb'       => "VARCHAR(255) NOT NULL DEFAULT ''",
            'features'    => 'TEXT NULL',
            'is_featured' => 'INTEGER NOT NULL DEFAULT 0',
            'sort'        => 'INTEGER NOT NULL DEFAULT 0',
            'is_active'   => 'INTEGER NOT NULL DEFAULT 1',
        ]],

        'addons' => ['columns' => [
            'id'    => '@id',
            'name'  => "VARCHAR(160) NOT NULL DEFAULT ''",
            'price' => "VARCHAR(40) NOT NULL DEFAULT ''",
            'blurb' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'sort'  => 'INTEGER NOT NULL DEFAULT 0',
        ]],

        'faqs' => ['columns' => [
            'id'       => '@id',
            'page'     => "VARCHAR(40) NOT NULL DEFAULT 'services'",
            'question' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'answer'   => 'TEXT NULL',
            'sort'     => 'INTEGER NOT NULL DEFAULT 0',
        ]],

        'testimonials' => ['columns' => [
            'id'        => '@id',
            'quote'     => 'TEXT NULL',
            'author'    => "VARCHAR(120) NOT NULL DEFAULT ''",
            'role'      => "VARCHAR(160) NOT NULL DEFAULT ''",
            'sort'      => 'INTEGER NOT NULL DEFAULT 0',
            'is_active' => 'INTEGER NOT NULL DEFAULT 1',
        ]],

        'portfolio' => ['columns' => [
            'id'            => '@id',
            'slug'          => "VARCHAR(190) NOT NULL DEFAULT ''",
            'title'         => "VARCHAR(190) NOT NULL DEFAULT ''",
            'client_name'   => "VARCHAR(190) NOT NULL DEFAULT ''",
            'sector'        => "VARCHAR(120) NOT NULL DEFAULT ''",
            'summary'       => "VARCHAR(400) NOT NULL DEFAULT ''",
            'body'          => 'TEXT NULL',
            'services_used' => 'TEXT NULL',
            'tech'          => 'TEXT NULL',
            'live_url'      => "VARCHAR(255) NOT NULL DEFAULT ''",
            'cover_image'   => "VARCHAR(255) NOT NULL DEFAULT ''",
            'completed_on'  => 'DATE NULL',
            'visibility'    => "VARCHAR(20) NOT NULL DEFAULT 'public'",
            'is_featured'   => 'INTEGER NOT NULL DEFAULT 0',
            'sort'          => 'INTEGER NOT NULL DEFAULT 0',
            'created_at'    => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_portfolio_slug' => ['unique' => false, 'columns' => 'slug'],
        ]],

        'products' => ['columns' => [
            'id'             => '@id',
            'slug'           => "VARCHAR(190) NOT NULL DEFAULT ''",
            'title'          => "VARCHAR(190) NOT NULL DEFAULT ''",
            'category'       => "VARCHAR(120) NOT NULL DEFAULT ''",
            'summary'        => "VARCHAR(400) NOT NULL DEFAULT ''",
            'body'           => 'TEXT NULL',
            'features'       => 'TEXT NULL',
            'tech'           => 'TEXT NULL',
            'price'          => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'sale_price'     => 'DECIMAL(10,2) NULL',
            'demo_url'       => "VARCHAR(255) NOT NULL DEFAULT ''",
            'cover_image'    => "VARCHAR(255) NOT NULL DEFAULT ''",
            'pages'          => 'INTEGER NOT NULL DEFAULT 0',
            'includes_setup' => 'INTEGER NOT NULL DEFAULT 1',
            'is_active'      => 'INTEGER NOT NULL DEFAULT 1',
            'is_featured'    => 'INTEGER NOT NULL DEFAULT 0',
            'sort'           => 'INTEGER NOT NULL DEFAULT 0',
            'created_at'     => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_products_slug' => ['unique' => false, 'columns' => 'slug'],
        ]],

        'orders' => ['columns' => [
            'id'            => '@id',
            'reference'     => "VARCHAR(40) NOT NULL DEFAULT ''",
            'product_id'    => '@fk NULL',
            'user_id'       => '@fk NULL',
            'buyer_name'    => "VARCHAR(160) NOT NULL DEFAULT ''",
            'buyer_email'   => "VARCHAR(190) NOT NULL DEFAULT ''",
            'buyer_phone'   => "VARCHAR(60) NOT NULL DEFAULT ''",
            'buyer_company' => "VARCHAR(160) NOT NULL DEFAULT ''",
            'amount'        => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'wants_setup'   => 'INTEGER NOT NULL DEFAULT 0',
            'notes'         => 'TEXT NULL',
            'admin_notes'   => 'TEXT NULL',
            'status'        => "VARCHAR(20) NOT NULL DEFAULT 'new'",
            'created_at'    => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_orders_status' => ['unique' => false, 'columns' => 'status'],
        ]],

        'projects' => ['columns' => [
            'id'                 => '@id',
            'user_id'            => '@fk NULL',
            'portfolio_id'       => '@fk NULL',
            'name'               => "VARCHAR(190) NOT NULL DEFAULT ''",
            'reference'          => "VARCHAR(40) NOT NULL DEFAULT ''",
            'status'             => "VARCHAR(20) NOT NULL DEFAULT 'building'",
            'owner_name'         => "VARCHAR(160) NOT NULL DEFAULT ''",
            'owner_email'        => "VARCHAR(190) NOT NULL DEFAULT ''",
            'owner_phone'        => "VARCHAR(60) NOT NULL DEFAULT ''",
            'company'            => "VARCHAR(190) NOT NULL DEFAULT ''",
            'domain'             => "VARCHAR(190) NOT NULL DEFAULT ''",
            'domain_registrar'   => "VARCHAR(160) NOT NULL DEFAULT ''",
            'domain_expires_on'  => 'DATE NULL',
            'hosting_provider'   => "VARCHAR(160) NOT NULL DEFAULT ''",
            'hosting_plan'       => "VARCHAR(160) NOT NULL DEFAULT ''",
            'hosting_expires_on' => 'DATE NULL',
            'server_ip'          => "VARCHAR(60) NOT NULL DEFAULT ''",
            'ssl_provider'       => "VARCHAR(160) NOT NULL DEFAULT ''",
            'ssl_expires_on'     => 'DATE NULL',
            'email_provider'     => "VARCHAR(160) NOT NULL DEFAULT ''",
            'email_accounts'     => 'INTEGER NOT NULL DEFAULT 0',
            'email_expires_on'   => 'DATE NULL',
            'care_plan'          => "VARCHAR(120) NOT NULL DEFAULT ''",
            'care_renews_on'     => 'DATE NULL',
            'launched_on'        => 'DATE NULL',
            'notes'              => 'TEXT NULL',
            'created_at'         => 'DATETIME NULL',
            'updated_at'         => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_projects_user' => ['unique' => false, 'columns' => 'user_id'],
        ]],

        'tickets' => ['columns' => [
            'id'         => '@id',
            'reference'  => "VARCHAR(40) NOT NULL DEFAULT ''",
            'project_id' => '@fk NULL',
            'user_id'    => '@fk NULL',
            'subject'    => "VARCHAR(255) NOT NULL DEFAULT ''",
            'category'   => "VARCHAR(30) NOT NULL DEFAULT 'support'",
            'priority'   => "VARCHAR(20) NOT NULL DEFAULT 'normal'",
            'status'     => "VARCHAR(20) NOT NULL DEFAULT 'open'",
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_tickets_user'   => ['unique' => false, 'columns' => 'user_id'],
            'idx_tickets_status' => ['unique' => false, 'columns' => 'status'],
        ]],

        'ticket_messages' => ['columns' => [
            'id'          => '@id',
            'ticket_id'   => '@fk NULL',
            'author_id'   => '@fk NULL',
            'author_name' => "VARCHAR(160) NOT NULL DEFAULT ''",
            'author_type' => "VARCHAR(20) NOT NULL DEFAULT 'client'",
            'body'        => 'TEXT NULL',
            'is_internal' => 'INTEGER NOT NULL DEFAULT 0',
            'created_at'  => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_messages_ticket' => ['unique' => false, 'columns' => 'ticket_id'],
        ]],

        'maintenance_logs' => ['columns' => [
            'id'                => '@id',
            'project_id'        => '@fk NULL',
            'title'             => "VARCHAR(255) NOT NULL DEFAULT ''",
            'kind'              => "VARCHAR(30) NOT NULL DEFAULT 'update'",
            'body'              => 'TEXT NULL',
            'performed_on'      => 'DATE NULL',
            'performed_by'      => "VARCHAR(160) NOT NULL DEFAULT ''",
            'visible_to_client' => 'INTEGER NOT NULL DEFAULT 1',
            'created_at'        => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_logs_project' => ['unique' => false, 'columns' => 'project_id'],
        ]],

        'enquiries' => ['columns' => [
            'id'          => '@id',
            'name'        => "VARCHAR(160) NOT NULL DEFAULT ''",
            'email'       => "VARCHAR(190) NOT NULL DEFAULT ''",
            'phone'       => "VARCHAR(60) NOT NULL DEFAULT ''",
            'company'     => "VARCHAR(160) NOT NULL DEFAULT ''",
            'service'     => "VARCHAR(120) NOT NULL DEFAULT ''",
            'budget'      => "VARCHAR(60) NOT NULL DEFAULT ''",
            'message'     => 'TEXT NULL',
            'status'      => "VARCHAR(20) NOT NULL DEFAULT 'new'",
            'admin_notes' => 'TEXT NULL',
            'mail_sent'   => 'INTEGER NOT NULL DEFAULT 0',
            'created_at'  => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_enquiries_status' => ['unique' => false, 'columns' => 'status'],
        ]],

        'activity_log' => ['columns' => [
            'id'         => '@id',
            'user_id'    => '@fk NULL',
            'actor'      => "VARCHAR(160) NOT NULL DEFAULT ''",
            'action'     => "VARCHAR(190) NOT NULL DEFAULT ''",
            'entity'     => "VARCHAR(60) NOT NULL DEFAULT ''",
            'entity_id'  => 'INTEGER NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NULL',
        ]],

        'login_attempts' => ['columns' => [
            'id'           => '@id',
            'identifier'   => "VARCHAR(190) NOT NULL DEFAULT ''",
            'attempted_at' => 'DATETIME NULL',
        ], 'indexes' => [
            'idx_attempts_id' => ['unique' => false, 'columns' => 'identifier'],
        ]],
    ];
}

/* ---------------------------------------------------------------------- */
/* driver differences                                                     */
/* ---------------------------------------------------------------------- */

/** Turn @id / @fk into something the current driver understands. */
function schema_type(string $def): string
{
    $mysql = db_driver() === 'mysql';
    return str_replace(
        ['@id', '@fk'],
        $mysql
            ? ['INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INT UNSIGNED']
            : ['INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER'],
        $def
    );
}

/** Table and column names only ever come from schema_tables(), but check anyway. */
function schema_safe_name(string $name): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new RuntimeException('Refusing to use an unexpected identifier: ' . $name);
    }
    return $name;
}

function db_table_exists(string $table): bool
{
    $table = schema_safe_name($table);
    if (db_driver() === 'mysql') {
        return (bool) db_value(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }
    return (bool) db_value(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
        [$table]
    );
}

/** Column names currently on a table. */
function db_table_columns(string $table): array
{
    $table = schema_safe_name($table);
    if (db_driver() === 'mysql') {
        $rows = db_all(
            'SELECT COLUMN_NAME AS name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    } else {
        /* PRAGMA cannot take a bound parameter, hence the checked name above. */
        $rows = db_all("PRAGMA table_info({$table})");
    }
    return array_map(fn($r) => $r['name'], $rows);
}

function db_index_exists(string $table, string $index): bool
{
    $table = schema_safe_name($table);
    $index = schema_safe_name($index);
    if (db_driver() === 'mysql') {
        return (bool) db_value(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );
    }
    return (bool) db_value(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = ?",
        [$index]
    );
}

/* ---------------------------------------------------------------------- */
/* create and migrate                                                     */
/* ---------------------------------------------------------------------- */

function schema_create_table(string $table, array $spec): void
{
    $table = schema_safe_name($table);
    $cols  = [];
    foreach ($spec['columns'] as $name => $def) {
        $cols[] = schema_safe_name($name) . ' ' . schema_type($def);
    }
    $tail = db_driver() === 'mysql'
        ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : '';
    db()->exec("CREATE TABLE IF NOT EXISTS {$table} (" . implode(', ', $cols) . "){$tail}");
}

/**
 * MySQL has no CREATE INDEX IF NOT EXISTS, so check first on both drivers.
 */
function schema_create_index(string $table, string $index, array $spec): void
{
    if (db_index_exists($table, $index)) {
        return;
    }
    $table  = schema_safe_name($table);
    $index  = schema_safe_name($index);
    $cols   = implode(', ', array_map('schema_safe_name', (array) explode(',', $spec['columns'])));
    $unique = !empty($spec['unique']) ? 'UNIQUE ' : '';
    db()->exec("CREATE {$unique}INDEX {$index} ON {$table} ({$cols})");
}

/** Everything, from nothing. Used by a fresh install. */
function schema_create(): void
{
    foreach (schema_tables() as $table => $spec) {
        schema_create_table($table, $spec);
        foreach ($spec['indexes'] ?? [] as $index => $ispec) {
            schema_create_index($table, $index, $ispec);
        }
    }
}

/**
 * Bring an existing database up to date without touching any data: create
 * tables that are missing, add columns that are missing, add indexes that are
 * missing. Nothing is ever dropped or altered in place.
 *
 * Returns a plain list of what it did, for showing to whoever ran it.
 */
function schema_migrate(): array
{
    $done = [];

    foreach (schema_tables() as $table => $spec) {
        if (!db_table_exists($table)) {
            schema_create_table($table, $spec);
            $done[] = "Created the {$table} table";
        } else {
            $have = db_table_columns($table);
            foreach ($spec['columns'] as $name => $def) {
                if (in_array($name, $have, true)) {
                    continue;
                }
                if (str_contains($def, '@id')) {
                    continue;   /* never try to bolt a primary key on afterwards */
                }
                $type = schema_type($def);
                /* SQLite refuses NOT NULL without a default on an existing table. */
                if (!str_contains(strtoupper($type), 'DEFAULT')) {
                    $type = str_ireplace(' NOT NULL', '', $type);
                }
                db()->exec('ALTER TABLE ' . schema_safe_name($table) .
                           ' ADD COLUMN ' . schema_safe_name($name) . ' ' . $type);
                $done[] = "Added {$table}.{$name}";
            }
        }

        foreach ($spec['indexes'] ?? [] as $index => $ispec) {
            if (!db_index_exists($table, $index)) {
                schema_create_index($table, $index, $ispec);
                $done[] = "Added the {$index} index";
            }
        }
    }

    return $done;
}

/** Which version the database thinks it is on. */
function schema_installed_version(): int
{
    try {
        $v = db_value("SELECT value FROM settings WHERE setting_key = 'schema.version'");
        return $v === null ? 1 : (int) $v;
    } catch (Throwable $e) {
        return 0;
    }
}

function schema_set_version(int $version): void
{
    setting_set('schema.version', (string) $version);
}

function schema_needs_update(): bool
{
    return schema_installed_version() < SCHEMA_VERSION;
}

/** Every table this application expects to exist. */
function schema_missing_tables(): array
{
    $missing = [];
    foreach (array_keys(schema_tables()) as $table) {
        if (!db_table_exists($table)) {
            $missing[] = $table;
        }
    }
    return $missing;
}

/** Drop everything. Only ever called from a deliberate fresh install. */
function schema_drop_all(): void
{
    if (db_driver() === 'mysql') {
        db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    }
    foreach (array_keys(schema_tables()) as $table) {
        db()->exec('DROP TABLE IF EXISTS ' . schema_safe_name($table));
    }
    if (db_driver() === 'mysql') {
        db()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
