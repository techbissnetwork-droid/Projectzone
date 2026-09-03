<?php
declare(strict_types=1);

/**
 * Idempotent schema upgrades. Safe to run repeatedly: every step checks
 * the live database first and skips what is already there.
 * Returns a list of human-readable lines describing what it did.
 */
function tb_migrate(PDO $pdo, string $driver = 'mysql'): array
{
    $done = [];
    $ts   = date('Y-m-d H:i:s');

    $tables = tb_tables($pdo, $driver);

    /* 1 · create any table the schema declares but the database lacks */
    $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (!preg_match('/CREATE TABLE\s+(\w+)/i', $stmt, $m)) {
            continue;
        }
        if (in_array(strtolower($m[1]), $tables, true)) {
            continue;
        }
        foreach (tb_ddl($stmt, $driver) as $piece) {
            $pdo->exec($piece);
        }
        $done[] = 'Created table ' . $m[1];
        $tables[] = strtolower($m[1]);
    }

    /* 2 · add columns that were introduced after the first release */
    $wanted = [
        'pages'    => [],
        'nav_items'=> [],
        'content_items' => [],
        'projects' => ['portfolio_id' => 'INT UNSIGNED NULL'],
        'users'    => ['company' => 'VARCHAR(160) NULL', 'must_change' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        'products' => ['sales_count' => 'INT NOT NULL DEFAULT 0'],
        'orders'   => [
            'payment_method_id' => 'INT UNSIGNED NULL',
            'paid_at'           => 'DATETIME NULL',
            'access_token'      => 'VARCHAR(64) NULL',
        ],
    ];
    /* 2b · columns that exist but are too narrow for what the code now writes.
             orders.payment_method holds a copy of payment_methods.name, which is
             VARCHAR(120); at VARCHAR(40) a longer method name made every
             checkout throw and lose the sale. ADD COLUMN above never revisits a
             column that already exists, so widening needs its own step. */
    $widen = [
        'orders' => ['payment_method' => ['VARCHAR(120) NULL', 120]],
    ];
    if ($driver !== 'sqlite') {
        foreach ($widen as $table => $cols) {
            if (!in_array(strtolower($table), $tables, true)) {
                continue;
            }
            foreach ($cols as $col => [$ddl, $want]) {
                $len = $pdo->query(
                    'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS'
                    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $table . "'"
                    . " AND COLUMN_NAME = '" . $col . "'"
                )->fetchColumn();
                if ($len !== false && $len !== null && (int)$len < $want) {
                    $pdo->exec('ALTER TABLE ' . $table . ' MODIFY ' . $col . ' ' . $ddl);
                    $done[] = 'Widened ' . $table . '.' . $col . ' to ' . $want;
                }
            }
        }
    }

    foreach ($wanted as $table => $cols) {
        if (!in_array(strtolower($table), $tables, true) || !$cols) {
            continue;
        }
        $have = tb_columns($pdo, $table, $driver);
        foreach ($cols as $col => $ddl) {
            if (in_array(strtolower($col), $have, true)) {
                continue;
            }
            $type = $driver === 'sqlite'
                ? preg_replace(['/\bINT UNSIGNED\b/i', '/\bTINYINT\(1\)/i', '/\bVARCHAR\(\d+\)/i'],
                               ['INTEGER', 'INTEGER', 'TEXT'], $ddl)
                : $ddl;
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $col . ' ' . $type);
            $done[] = 'Added ' . $table . '.' . $col;
        }
    }

    /* 3 · insert settings introduced since this install was created */
    $existing = [];
    foreach ($pdo->query('SELECT skey FROM settings')->fetchAll(PDO::FETCH_COLUMN) as $k) {
        $existing[$k] = true;
    }
    $ins = $pdo->prepare('INSERT INTO settings (skey, svalue, updated_at) VALUES (?,?,?)');
    $added = 0;
    foreach (Settings::defaults() as $k => $v) {
        if (!isset($existing[$k])) {
            $ins->execute([$k, $v, $ts]);
            $added++;
        }
    }
    if ($added) {
        $done[] = 'Added ' . $added . ' new setting' . ($added === 1 ? '' : 's');
    }

    /* 3a · the home page order was rearranged so that what we sell, who we
            build it for and the proof come before the technical depth. Only
            applied to a site still on the original order — an administrator who
            has dragged the sections into their own arrangement keeps it. */
    $wasOrder = 'services,arch,process,work,transform,pillars,marketplace,quote';
    $nowOrder = 'services,transform,work,marketplace,process,arch,pillars,quote';
    $curOrder = (string)($pdo->query("SELECT svalue FROM settings WHERE skey='home_sections'")->fetchColumn() ?: '');
    if ($curOrder === $wasOrder) {
        $pdo->prepare('UPDATE settings SET svalue = ?, updated_at = ? WHERE skey = ?')
            ->execute([$nowOrder, $ts, 'home_sections']);
        $done[] = 'Reordered the home page sections';
    }

    /* 3c · captions written as three-part phrases, shortened to the one word
            that carries them. Applied only where the text is still exactly what
            was seeded, so anything an administrator has rewritten is left as
            they wrote it. */
    $shorter = [
        'stat'   => ['Services under one roof' => 'Services',
                     'Delivery model'          => 'Delivery',
                     'Infrastructure uptime'   => 'Uptime'],
        'pillar' => ['certificate valid · auto-renew' => 'auto-renewed',
                     'auto-scaling · 3 regions'       => '3 regions',
                     '99.98% · 30-day window'         => '99.98% uptime',
                     'containerised · reproducible'   => 'containerised',
                     'responsive · touch-first'       => 'touch-first'],
        'arch'   => ['Your goals, customers, operations'            => 'Goals & operations',
                     'Architecture · design system · API contracts' => 'Architecture & APIs',
                     'iOS · Android · internal'                     => 'iOS & Android',
                     'Checkout & settlement'                        => 'Checkout',
                     'Domain communication'                         => 'On your domain',
                     'Single source of truth'                       => 'One source of truth'],
    ];
    if (in_array('content_items', $tables, true)) {
        /* A stat's caption is its label; a pillar's and an arch node's is its title. */
        $trimmed = 0;
        foreach ($shorter as $kind => $map) {
            $col = $kind === 'stat' ? 'label' : 'title';
            $up  = $pdo->prepare('UPDATE content_items SET ' . $col . ' = ? WHERE kind = ? AND ' . $col . ' = ?');
            foreach ($map as $was => $now) {
                $up->execute([$now, $kind, $was]);
                $trimmed += $up->rowCount();
            }
        }
        if ($trimmed) {
            $done[] = 'Shortened ' . $trimmed . ' caption' . ($trimmed === 1 ? '' : 's');
        }
    }

    $rewritten = [
        'hero_eyebrow'  => ['Digital transformation · est. for the next decade', 'Digital transformation'],
        'hero_lede'     => ['From offline operations to a complete online ecosystem — TECHBISS builds, launches and powers everything your business needs to grow online.',
                            'From offline to online — we build, launch and run everything your business needs to grow.'],
        'services_lede' => ['Commission one module, or let us run the entire stack — domain to analytics — as a single system.',
                            'Take one module, or let us run the whole stack as a single system.'],
        'arch_lede'     => ['Brand, front-end, application, backend, database, payments, hosting, security and analytics — designed as one architecture so nothing is bolted on later.',
                            'Site, app, database, payments, hosting and security — designed as one system, not bolted together.'],
        'pillars_lede'  => ['The things nobody notices until they fail — certificates, backups, response times, capacity — are the things we monitor on your behalf.',
                            'Certificates, backups, uptime and capacity — the things nobody notices until they fail. We watch them.'],
        'mkt_lede'      => ['Complete systems we have already built and tested. Buy the source, we install and brand it for you.',
                            'Systems already built and tested. Buy it, we install and brand it for you.'],
    ];
    $upSet = $pdo->prepare('UPDATE settings SET svalue = ?, updated_at = ? WHERE skey = ? AND svalue = ?');
    $reworded = 0;
    foreach ($rewritten as $key => [$was, $now]) {
        $upSet->execute([$now, $ts, $key, $was]);
        $reworded += $upSet->rowCount();
    }
    if ($reworded) {
        $done[] = 'Shortened ' . $reworded . ' block' . ($reworded === 1 ? '' : 's') . ' of copy';
    }

    /* 3b · a starter payment method, so checkout is never a dead end */
    if (in_array('payment_methods', $tables, true)
        && (int)$pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn() === 0) {
        require_once __DIR__ . '/seed.php';
        tb_seed_payments($pdo, $ts);
        $done[] = 'Added the default bank-transfer payment method';
    }

    /* 4 · seed content blocks, pages and menus only when they are empty,
           so an existing site never has its own content duplicated */
    if (in_array('content_items', $tables, true)
        && (int)$pdo->query('SELECT COUNT(*) FROM content_items')->fetchColumn() === 0) {
        require_once __DIR__ . '/seed.php';
        tb_seed_content($pdo, $ts);
        $done[] = 'Seeded the editable content blocks, starter pages and menus';
    }

    if (!$done) {
        $done[] = 'Everything is already up to date.';
    }
    return $done;
}

/**
 * The schema is written for MySQL. Translate one CREATE TABLE for SQLite,
 * splitting its inline KEY definitions into separate CREATE INDEX statements.
 * @return array<int,string> statements to run in order
 */
function tb_ddl(string $stmt, string $driver): array
{
    if ($driver !== 'sqlite') {
        return [$stmt];
    }
    preg_match('/CREATE TABLE\s+(\w+)/i', $stmt, $m);
    $table = $m[1] ?? 'unknown';

    $indexes = [];
    $stmt = preg_replace_callback(
        '/,\s*(UNIQUE\s+)?KEY\s+(\w+)\s*\(([^)]+)\)/i',
        static function (array $mm) use (&$indexes): string {
            $indexes[] = [trim($mm[1] ?? '') !== '', $mm[2], $mm[3]];
            return '';
        },
        $stmt
    ) ?? $stmt;

    $stmt = str_ireplace('INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $stmt);
    $stmt = preg_replace('/\)\s*ENGINE=InnoDB[^;]*/i', ')', $stmt) ?? $stmt;
    $stmt = preg_replace([
        '/\bINT UNSIGNED\b/i', '/\bTINYINT\(1\)/i', '/\bDECIMAL\(\d+,\d+\)/i',
        '/\bVARCHAR\(\d+\)/i', '/\bMEDIUMTEXT\b/i', '/\bINT\b(?!EGER)/i', '/\bDATETIME\b|\bDATE\b/i',
    ], ['INTEGER', 'INTEGER', 'REAL', 'TEXT', 'TEXT', 'INTEGER', 'TEXT'], $stmt) ?? $stmt;

    $out = [$stmt];
    foreach ($indexes as $i => [$uniq, $name, $cols]) {
        $out[] = sprintf('CREATE %sINDEX IF NOT EXISTS %s_%s ON %s (%s)',
            $uniq ? 'UNIQUE ' : '', $name, $table, $table, $cols);
    }
    return $out;
}

/** @return array<int,string> lowercase table names */
function tb_tables(PDO $pdo, string $driver): array
{
    $sql = $driver === 'sqlite'
        ? "SELECT name FROM sqlite_master WHERE type='table'"
        : 'SHOW TABLES';
    return array_map('strtolower', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

/** @return array<int,string> lowercase column names */
function tb_columns(PDO $pdo, string $table, string $driver): array
{
    if ($driver === 'sqlite') {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn($r) => strtolower((string)$r['name']), $rows);
    }
    $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn($r) => strtolower((string)$r['Field']), $rows);
}
