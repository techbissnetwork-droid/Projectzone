<?php
declare(strict_types=1);

/**
 * TECHBISS — setup wizard.
 *
 * Runs before any configuration exists: it detects the site URL, collects the
 * database credentials, writes config/config.php, creates the schema, loads the
 * baseline content and creates the first administrator.
 *
 * Deliberately standalone — it does not include the application bootstrap,
 * because the bootstrap requires the very config file this script writes.
 *
 * Delete this file once setup is finished.
 *
 * Command line equivalent:
 *   php install.php --db-name=techbiss --db-user=root --db-pass=secret \
 *       --name="Your Name" --email=you@example.com --password='a-strong-password'
 */

// ---------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------
define('TB_ROOT', __DIR__);
define('TB_CONFIG', TB_ROOT . '/config/config.php');

const TB_REQUIRED_EXT = ['pdo_mysql', 'mbstring', 'json', 'fileinfo'];
const TB_OPTIONAL_EXT = ['gd' => 'image thumbnails', 'intl' => 'accented slug transliteration', 'zip' => 'archive export'];
const TB_WRITABLE     = ['config', 'storage/cache', 'storage/logs', 'uploads/media', 'uploads/thumbs'];

$cli = PHP_SAPI === 'cli';

// ---------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------

/** Scheme + host for this request, honouring a reverse proxy. */
function tb_detect_origin(): string
{
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';

    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = trim(explode(',', $host)[0]);
    $host = preg_replace('/[^A-Za-z0-9:._\-\[\]]/', '', $host) ?: 'localhost';

    return ($https ? 'https://' : 'http://') . $host;
}

/** The sub-directory the site is served from, derived from this script's URL. */
function tb_detect_base_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '') {
        return '';
    }
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    return ($dir === '' || $dir === '.') ? '' : $dir;
}

function tb_is_https(): bool
{
    return str_starts_with(tb_detect_origin(), 'https://');
}

/** Best guess at the server's timezone, falling back to UTC. */
function tb_detect_timezone(): string
{
    $tz = @date_default_timezone_get();
    if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        return $tz;
    }
    return 'UTC';
}

/** @return array{ok:bool,required:array,optional:array,writable:array,php:bool} */
function tb_requirements(): array
{
    $required = [];
    foreach (TB_REQUIRED_EXT as $ext) {
        $required[$ext] = extension_loaded($ext);
    }
    $optional = [];
    foreach (TB_OPTIONAL_EXT as $ext => $why) {
        $optional[$ext] = ['loaded' => extension_loaded($ext), 'why' => $why];
    }
    $writable = [];
    foreach (TB_WRITABLE as $rel) {
        $path = TB_ROOT . '/' . $rel;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $writable[$rel] = is_dir($path) && is_writable($path);
    }
    $php = PHP_VERSION_ID >= 80100;

    $ok = $php && !in_array(false, $required, true) && !in_array(false, $writable, true);
    return ['ok' => $ok, 'required' => $required, 'optional' => $optional, 'writable' => $writable, 'php' => $php];
}

/**
 * Open a PDO connection, optionally creating the database first.
 * @return array{ok:bool,pdo:?PDO,error:string}
 */
function tb_connect(array $db, bool $createIfMissing = false): array
{
    $socket = trim((string) ($db['socket'] ?? ''));
    $dsnBase = $socket !== ''
        ? 'mysql:unix_socket=' . $socket
        : sprintf('mysql:host=%s;port=%d', $db['host'], (int) $db['port']);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        // Connect without a database first so we can create it if needed.
        $server = new PDO($dsnBase, $db['user'], $db['pass'], $options);
    } catch (PDOException $e) {
        return ['ok' => false, 'pdo' => null, 'error' => 'Could not reach the database server: ' . $e->getMessage()];
    }

    $name = (string) $db['name'];
    if (!preg_match('/^[A-Za-z0-9_$\-]{1,64}$/', $name)) {
        return ['ok' => false, 'pdo' => null, 'error' => 'That database name contains characters MySQL will not accept.'];
    }

    $exists = (bool) $server->query(
        'SELECT 1 FROM information_schema.schemata WHERE schema_name = ' . $server->quote($name)
    )->fetchColumn();

    if (!$exists) {
        if (!$createIfMissing) {
            return ['ok' => false, 'pdo' => null,
                    'error' => 'The database "' . $name . '" does not exist. Tick “Create it for me” or create it yourself first.'];
        }
        try {
            $server->exec('CREATE DATABASE `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            return ['ok' => false, 'pdo' => null,
                    'error' => 'The database does not exist and could not be created (' . $e->getMessage()
                             . '). Create it in your hosting panel, then try again.'];
        }
    }

    try {
        $pdo = new PDO($dsnBase . ';dbname=' . $name . ';charset=utf8mb4', $db['user'], $db['pass'], $options);
    } catch (PDOException $e) {
        return ['ok' => false, 'pdo' => null, 'error' => 'Connected to the server but not to that database: ' . $e->getMessage()];
    }

    return ['ok' => true, 'pdo' => $pdo, 'error' => ''];
}

/** Execute a .sql file statement by statement. */
function tb_run_sql(PDO $pdo, string $file): int
{
    if (!is_file($file)) {
        throw new RuntimeException(basename($file) . ' is missing from the database directory.');
    }
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $count = 0;
    foreach (preg_split('/;\s*[\r\n]/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '' || $statement === ';') {
            continue;
        }
        $pdo->exec($statement);
        $count++;
    }
    return $count;
}

/** Render config/config.php from the sample, with the collected values substituted. */
function tb_write_config(array $db, array $site): array
{
    $q = static fn (string $v): string => var_export($v, true);

    $config = <<<PHP
<?php
/**
 * TECHBISS — application configuration.
 *
 * Written by the setup wizard on {DATE}.
 * Keep this file out of version control: it holds your database credentials
 * and the application key.
 */

return [
    // ---------------------------------------------------------------------
    // Database
    // ---------------------------------------------------------------------
    'db' => [
        'host'    => {DB_HOST},
        'port'    => {DB_PORT},
        'name'    => {DB_NAME},
        'user'    => {DB_USER},
        'pass'    => {DB_PASS},
        'charset' => 'utf8mb4',
        'socket'  => {DB_SOCKET},
    ],

    // ---------------------------------------------------------------------
    // Site
    //
    // Leave 'url' and 'base_path' empty to have them detected per request —
    // useful if the site moves domain or directory. They are filled in here
    // because the wizard detected them, which is marginally faster and makes
    // canonical URLs deterministic behind a proxy.
    // ---------------------------------------------------------------------
    'site' => [
        'url'       => {SITE_URL},
        'base_path' => {BASE_PATH},
        'timezone'  => {TIMEZONE},
        'locale'    => 'en',
        'debug'     => false,
    ],

    // ---------------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------------
    'security' => [
        'app_key'            => {APP_KEY},
        'session_name'       => 'techbiss_session',
        'session_lifetime'   => 7200,
        // Set true once every page is served over HTTPS.
        'cookie_secure'      => {COOKIE_SECURE},
        'login_max_attempts' => 6,
        'login_lockout'      => 900,
    ],

    // ---------------------------------------------------------------------
    // Uploads
    // ---------------------------------------------------------------------
    'uploads' => [
        'dir'          => __DIR__ . '/uploads',
        'max_bytes'    => 6 * 1024 * 1024,
        'max_width'    => 6000,
        'max_height'   => 6000,
        'allowed_mime' => [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'image/svg+xml'   => 'svg',
            'application/pdf' => 'pdf',
        ],
        'thumb_width'  => 480,
    ],

    // ---------------------------------------------------------------------
    // Mail — 'mail' uses PHP mail(), 'log' writes to storage/logs/mail.log,
    // 'none' disables delivery.
    // ---------------------------------------------------------------------
    'mail' => [
        'driver' => {MAIL_DRIVER},
        'from'   => {MAIL_FROM},
        'name'   => {SITE_NAME},
    ],

    // ---------------------------------------------------------------------
    // Cache
    // ---------------------------------------------------------------------
    'cache' => [
        'enabled' => true,
        'dir'     => __DIR__ . '/storage/cache',
        'ttl'     => 300,
    ],
];
PHP;

    $replacements = [
        '{DATE}'          => date('j F Y \a\t H:i'),
        '{DB_HOST}'       => $q((string) $db['host']),
        '{DB_PORT}'       => (string) (int) $db['port'],
        '{DB_NAME}'       => $q((string) $db['name']),
        '{DB_USER}'       => $q((string) $db['user']),
        '{DB_PASS}'       => $q((string) $db['pass']),
        '{DB_SOCKET}'     => $q((string) ($db['socket'] ?? '')),
        '{SITE_URL}'      => $q((string) $site['url']),
        '{BASE_PATH}'     => $q((string) $site['base_path']),
        '{TIMEZONE}'      => $q((string) $site['timezone']),
        '{APP_KEY}'       => $q(bin2hex(random_bytes(32))),
        '{COOKIE_SECURE}' => $site['https'] ? 'true' : 'false',
        '{MAIL_DRIVER}'   => $q((string) $site['mail_driver']),
        '{MAIL_FROM}'     => $q((string) $site['mail_from']),
        '{SITE_NAME}'     => $q((string) $site['name']),
    ];

    $config = str_replace(array_keys($replacements), array_values($replacements), $config);

    $dir = dirname(TB_CONFIG);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'error' => 'The config directory is not writable. Give it write permission (755 or 775) and try again.', 'config' => $config];
    }
    if (@file_put_contents(TB_CONFIG, $config, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'config/config.php could not be written.', 'config' => $config];
    }
    @chmod(TB_CONFIG, 0640);

    return ['ok' => true, 'error' => '', 'config' => $config];
}

/** Apply the settings the wizard collected to the seeded settings table. */
function tb_apply_settings(PDO $pdo, array $site): void
{
    $map = [
        'site_name'          => $site['name'],
        'contact_email'      => $site['email'],
        'sales_email'        => $site['email'],
        'support_email'      => $site['email'],
        'notification_email' => $site['email'],
        'seo_default_title'  => $site['name'] . ' — Your Digital Business Starts Here',
        // Otherwise a renamed company still gets "| TECHBISS" appended to every title.
        'seo_title_suffix'   => ' | ' . $site['name'],
        'copyright'          => '© {year} ' . $site['name'] . '. All rights reserved.',
    ];
    $stmt = $pdo->prepare('UPDATE settings SET value = ?, updated_at = ? WHERE key_name = ?');
    $now  = date('Y-m-d H:i:s');
    foreach ($map as $key => $value) {
        if ((string) $value !== '') {
            $stmt->execute([$value, $now, $key]);
        }
    }
}

/**
 * Clear the file cache.
 *
 * Settings, navigation and homepage sections are cached to disk. If a previous
 * run left entries behind, a fresh install would serve the old company name and
 * menu until the cache expired, so setup always starts from a clean slate.
 */
function tb_clear_cache(): int
{
    $dir = TB_ROOT . '/storage/cache';
    if (!is_dir($dir)) {
        return 0;
    }
    $n = 0;
    foreach (glob($dir . '/*.cache') ?: [] as $file) {
        if (@unlink($file)) {
            $n++;
        }
    }
    return $n;
}

/** Has setup already been completed? */
/** The address the request came from, used only for throttling. */
function tb_client_ip(): string
{
    // REMOTE_ADDR only — a forwarded-for header is written by whoever is
    // calling, so trusting it would hand an attacker an unlimited number of
    // identities to spread guesses across.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Check an existing administrator's credentials.
 *
 * The upgrade path changes a live database, so it cannot be open to anyone who
 * finds install.php. Signing in as an existing administrator is the same bar
 * the admin area itself sets — including the same lockout, so this page cannot
 * be used to guess a password without the limit the admin login applies.
 *
 * @return array{ok:bool,name:string,error:string}
 */
function tb_verify_admin(PDO $pdo, string $email, string $password): array
{
    if ($email === '' || $password === '') {
        return ['ok' => false, 'name' => '', 'error' => 'Enter the email address and password of an existing administrator.'];
    }

    $ip     = tb_client_ip();
    $window = date('Y-m-d H:i:s', time() - 900);

    try {
        $count = $pdo->prepare('SELECT COUNT(*) FROM login_attempts
                                WHERE successful = 0 AND created_at > ? AND (identifier = ? OR ip_address = ?)');
        $count->execute([$window, $email, $ip]);
        if ((int) $count->fetchColumn() >= 5) {
            return ['ok' => false, 'name' => '', 'error' => 'Too many failed attempts. Wait fifteen minutes and try again.'];
        }
    } catch (Throwable) {
        // An older database may not have the table yet; the password check below
        // still stands, so carry on rather than locking the owner out.
    }

    try {
        $stmt = $pdo->prepare('SELECT a.name, a.password_hash, a.is_active, r.slug AS role
                               FROM admins a JOIN roles r ON r.id = a.role_id
                               WHERE a.email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['ok' => false, 'name' => '', 'error' => 'The database could not be read: ' . $e->getMessage()];
    }

    // Always spend the time hashing, so a missing account and a wrong password
    // take the same length of time to answer.
    $hash = is_array($row) ? (string) $row['password_hash'] : '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1MlGKGIry';
    $ok   = password_verify($password, $hash) && is_array($row)
        && (int) $row['is_active'] === 1 && (string) $row['role'] === 'super-admin';

    tb_record_attempt($pdo, $email, $ip, $ok);

    if (!$ok) {
        // One message for every failure: which of the four reasons applied is
        // not something a stranger should be able to learn from the answer.
        return ['ok' => false, 'name' => '', 'error' => 'Those credentials do not match an active super administrator.'];
    }

    return ['ok' => true, 'name' => (string) $row['name'], 'error' => ''];
}

/** Log an attempt in the same table the admin login uses. */
function tb_record_attempt(PDO $pdo, string $email, string $ip, bool $success): void
{
    try {
        $pdo->prepare('INSERT INTO login_attempts (identifier, ip_address, successful, created_at) VALUES (?, ?, ?, ?)')
            ->execute([mb_substr($email, 0, 190), $ip, $success ? 1 : 0, date('Y-m-d H:i:s')]);
        if ($success) {
            $pdo->prepare('DELETE FROM login_attempts WHERE successful = 0 AND (identifier = ? OR ip_address = ?)')
                ->execute([$email, $ip]);
        }
    } catch (Throwable) {
        // Recording is best effort; it must never block a legitimate upgrade.
    }
}

/**
 * Add an administrator to a database that already has one.
 *
 * @return array{ok:bool,error:string}
 */
function tb_add_admin(PDO $pdo, string $name, string $email, string $password): array
{
    try {
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'super-admin'")->fetchColumn();
        if ($roleId === 0) {
            return ['ok' => false, 'error' => 'The super-admin role is missing from this database.'];
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
        $check->execute([$email]);
        if ((int) $check->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'An administrator with that email address already exists.'];
        }

        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO admins (role_id, name, email, password_hash, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        )->execute([$roleId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $now, $now]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Delete install.php so the wizard cannot be reached again.
 *
 * Deleting a file over FTP or a hosting file manager is a step people skip,
 * and an installer left in place is the one file on the site that must not
 * stay. Offering the button means it usually does get done.
 *
 * @return array{ok:bool,error:string}
 */
function tb_lock_installer(): array
{
    $file = __FILE__;
    if (!is_file($file)) {
        return ['ok' => true, 'error' => ''];
    }
    if (!is_writable($file) || !is_writable(dirname($file))) {
        return ['ok' => false, 'error' => 'The web server cannot delete install.php — its permissions do not allow it. Delete the file yourself.'];
    }
    if (!@unlink($file)) {
        return ['ok' => false, 'error' => 'install.php could not be deleted. Delete the file yourself.'];
    }

    return ['ok' => true, 'error' => ''];
}

function tb_already_installed(): bool
{
    if (!is_file(TB_CONFIG)) {
        return false;
    }
    try {
        /** @var array $cfg */
        $cfg = require TB_CONFIG;
        $r = tb_connect($cfg['db'] ?? []);
        if (!$r['ok']) {
            return false;
        }
        $exists = $r['pdo']->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
        if (!$exists) {
            return false;
        }
        return (int) $r['pdo']->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Do the actual work: write config, build the schema, seed, create the admin.
 * @return array{ok:bool,error:string,steps:array<int,string>}
 */
function tb_install(array $db, array $site, array $admin, bool $demo, bool $createDb): array
{
    $steps = [];

    $conn = tb_connect($db, $createDb);
    if (!$conn['ok']) {
        return ['ok' => false, 'error' => $conn['error'], 'steps' => $steps];
    }
    $pdo = $conn['pdo'];
    $steps[] = 'Connected to ' . $db['name'];

    $written = tb_write_config($db, $site);
    if (!$written['ok']) {
        return ['ok' => false, 'error' => $written['error'], 'steps' => $steps, 'config' => $written['config']];
    }
    $steps[] = 'Wrote config/config.php with a generated application key';

    try {
        $n = tb_run_sql($pdo, TB_ROOT . '/database/schema.sql');
        $steps[] = 'Created the database schema (' . $n . ' statements)';

        $seeded = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 0;
        if (!$seeded) {
            $n = tb_run_sql($pdo, TB_ROOT . '/database/seed.sql');
            $steps[] = 'Loaded roles, permissions, settings, navigation, services, industries and FAQs';
        } else {
            $steps[] = 'Baseline content already present — left untouched';
        }

        if ($demo) {
            tb_run_sql($pdo, TB_ROOT . '/database/demo-content.sql');
            $steps[] = 'Loaded the optional demo content (fictional clients — remove before launch)';
        }

        tb_apply_settings($pdo, $site);
        $steps[] = 'Applied your company name and contact email';

        tb_clear_cache();
        $steps[] = 'Cleared the cache so the site reads the new settings immediately';

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'super-admin'")->fetchColumn();
        if ($roleId === 0) {
            throw new RuntimeException('The super-admin role is missing; seed.sql may not have loaded.');
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
        $check->execute([$admin['email']]);
        if ((int) $check->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'An administrator with that email address already exists.', 'steps' => $steps];
        }

        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare(
            'INSERT INTO admins (role_id, name, email, password_hash, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        $ins->execute([
            $roleId,
            $admin['name'],
            $admin['email'],
            password_hash($admin['password'], PASSWORD_DEFAULT),
            $now,
            $now,
        ]);
        $steps[] = 'Created your administrator account';
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'steps' => $steps];
    }

    return ['ok' => true, 'error' => '', 'steps' => $steps];
}

// =====================================================================
// Command line
// =====================================================================
/**
 * Bring an already-installed database up to the current schema.
 *
 * Reads config/config.php for the connection, so there is nothing to re-enter,
 * and only ever adds: new tables, new columns, new indexes, and the permission
 * and taxonomy rows a new feature needs. Nothing is dropped or overwritten.
 */
function tb_upgrade(bool $dryRun = false): int
{
    if (!is_file(TB_CONFIG)) {
        fwrite(STDERR, "config/config.php is missing — there is nothing installed to upgrade.\n");
        return 1;
    }
    $config = require TB_CONFIG;
    $db     = $config['db'] ?? [];

    $conn = tb_connect([
        'host'   => (string) ($db['host'] ?? '127.0.0.1'),
        'port'   => (int) ($db['port'] ?? 3306),
        'name'   => (string) ($db['name'] ?? ''),
        'user'   => (string) ($db['user'] ?? ''),
        'pass'   => (string) ($db['pass'] ?? ''),
        'socket' => (string) ($db['socket'] ?? ''),
    ]);
    if (!$conn['ok'] || !$conn['pdo'] instanceof PDO) {
        fwrite(STDERR, "Could not reach the database: " . (string) $conn['error'] . "\n");
        return 1;
    }
    $pdo = $conn['pdo'];

    require_once TB_ROOT . '/includes/Core/Migrator.php';
    $migrator = new Techbiss\Core\Migrator($pdo, TB_ROOT . '/database/schema.sql');

    try {
        $pending = $migrator->pending();
    } catch (Throwable $e) {
        fwrite(STDERR, "The schema could not be read: " . $e->getMessage() . "\n");
        return 1;
    }

    $count = count($pending['tables']) + count($pending['columns'])
           + count($pending['indexes']) + count($pending['data']);

    // Counted against the live data but rolled back, so the plan below reports
    // what will really change rather than an estimate.
    try {
        $copyPlan = $migrator->refreshCopy(true);
    } catch (Throwable $e) {
        fwrite(STDERR, "The copy refresh could not run: " . $e->getMessage() . "\n");
        return 1;
    }

    if ($count === 0 && $copyPlan['rows'] === 0) {
        echo "Database is already up to date. Nothing to do.\n";
        if ($pending['mismatched'] !== []) {
            echo "\nWorth a look — these columns exist but no longer match schema.sql:\n";
            foreach ($pending['mismatched'] as $m) {
                echo "  · $m\n";
            }
            echo "Nothing was changed: altering a populated column is your call, not this script's.\n";
        }
        return 0;
    }

    echo "Pending changes\n" . str_repeat('=', 46) . "\n";
    foreach ($pending['tables'] as $t) {
        echo "  + table   " . $t['name'] . "\n";
    }
    foreach ($pending['columns'] as $c) {
        echo "  + column  " . $c['table'] . '.' . $c['column'] . "\n";
    }
    foreach ($pending['indexes'] as $i) {
        echo "  + index   " . $i['name'] . ' on ' . $i['table'] . "\n";
    }
    foreach ($pending['data'] as $d) {
        echo "  · " . $d['label'] . "\n";
    }
    if ($copyPlan['rows'] > 0) {
        echo "  · " . $copyPlan['rows'] . " piece" . ($copyPlan['rows'] === 1 ? '' : 's')
            . " of seeded copy brought up to the current wording\n";
    }
    echo "\n";

    if ($dryRun) {
        echo "Dry run: nothing was changed. Run again without --dry-run to apply.\n";
        return 0;
    }

    try {
        $log = $migrator->apply();
    } catch (Throwable $e) {
        fwrite(STDERR, "The upgrade stopped: " . $e->getMessage() . "\n"
            . "Nothing after that point was applied. Fix the cause and run again — "
            . "the steps that did succeed will simply be skipped.\n");
        return 1;
    }

    foreach ($log as $line) {
        echo "  ✓ $line\n";
    }

    try {
        $copy = $migrator->refreshCopy(false);
    } catch (Throwable $e) {
        fwrite(STDERR, "The schema changes were applied, but the copy refresh failed: "
            . $e->getMessage() . "\nRun the upgrade again to finish it.\n");
        return 1;
    }
    if ($copy['rows'] > 0) {
        echo "  ✓ Updated " . $copy['rows'] . " piece" . ($copy['rows'] === 1 ? '' : 's')
            . " of seeded copy (anything you had edited yourself was left alone)\n";
    }

    tb_clear_cache();
    echo "  ✓ Cleared the cache\n";

    if ($pending['mismatched'] !== []) {
        echo "\nWorth a look — these columns exist but no longer match schema.sql:\n";
        foreach ($pending['mismatched'] as $m) {
            echo "  · $m\n";
        }
        echo "Nothing was changed: altering a populated column is your call, not this script's.\n";
    }

    echo "\nUpgrade complete.\n";
    return 0;
}

if ($cli) {
    $args = getopt('', [
        'db-host::', 'db-port::', 'db-name::', 'db-user::', 'db-pass::', 'db-socket::',
        'name::', 'email::', 'password::', 'site-name::', 'site-url::',
        'demo', 'create-db', 'force', 'check', 'upgrade', 'dry-run',
    ]);

    $req = tb_requirements();
    echo "TECHBISS setup\n" . str_repeat('=', 46) . "\n";
    printf("  PHP %s %s\n", PHP_VERSION, $req['php'] ? '✓' : '✗ (8.1+ required)');
    foreach ($req['required'] as $ext => $ok) {
        printf("  ext %-10s %s\n", $ext, $ok ? '✓' : '✗ required');
    }
    foreach ($req['optional'] as $ext => $info) {
        printf("  ext %-10s %s\n", $ext, $info['loaded'] ? '✓' : '– optional (' . $info['why'] . ')');
    }
    foreach ($req['writable'] as $dir => $ok) {
        printf("  dir %-16s %s\n", $dir, $ok ? 'writable ✓' : 'NOT WRITABLE ✗');
    }
    echo "\n";

    if (isset($args['check'])) {
        exit($req['ok'] ? 0 : 1);
    }
    if (!$req['ok']) {
        fwrite(STDERR, "Requirements are not met. Fix the items marked ✗ and run again.\n");
        exit(1);
    }
    // Upgrading an existing install: add whatever the current schema has and
    // this database does not. Purely additive, so it never needs credentials
    // beyond the ones already in config/config.php.
    if (isset($args['upgrade'])) {
        exit(tb_upgrade(isset($args['dry-run'])));
    }

    if (tb_already_installed() && !isset($args['force'])) {
        fwrite(STDERR, "TECHBISS is already installed.\n"
            . "  To bring its database up to date, run:  php install.php --upgrade\n"
            . "  To wipe it and start over, pass --force (this DESTROYS existing data).\n");
        exit(1);
    }

    $db = [
        'host'   => (string) ($args['db-host'] ?? '127.0.0.1'),
        'port'   => (int) ($args['db-port'] ?? 3306),
        'name'   => (string) ($args['db-name'] ?? 'techbiss'),
        'user'   => (string) ($args['db-user'] ?? 'root'),
        'pass'   => (string) ($args['db-pass'] ?? ''),
        'socket' => (string) ($args['db-socket'] ?? ''),
    ];
    $email = (string) ($args['email'] ?? '');
    $pass  = (string) ($args['password'] ?? '');

    if ($email === '' || $pass === '') {
        echo "Usage:\n  php install.php \\\n"
           . "    --db-name=techbiss --db-user=USER --db-pass=SECRET \\\n"
           . "    --name=\"Your Name\" --email=you@example.com --password='a-strong-password' \\\n"
           . "    [--db-host=127.0.0.1] [--db-port=3306] [--db-socket=/path/mysqld.sock] \\\n"
           . "    [--site-name=TECHBISS] [--site-url=https://example.com] [--create-db] [--demo]\n\n"
           . "  --check     report requirements only\n"
           . "  --upgrade   bring an existing install's database up to date (additive, safe)\n"
           . "  --dry-run   with --upgrade, list what would change and stop\n"
           . "  --force     reload the schema over an existing installation (DESTRUCTIVE)\n";
        exit($email === '' && $pass === '' ? 0 : 1);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "That email address is not valid.\n");
        exit(1);
    }
    if (strlen($pass) < 10 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        fwrite(STDERR, "The password must be at least 10 characters and contain both letters and numbers.\n");
        exit(1);
    }

    $siteName = (string) ($args['site-name'] ?? 'TECHBISS');
    $site = [
        'url'         => rtrim((string) ($args['site-url'] ?? ''), '/'),
        'base_path'   => '',
        'timezone'    => tb_detect_timezone(),
        'https'       => str_starts_with((string) ($args['site-url'] ?? ''), 'https://'),
        'name'        => $siteName,
        'email'       => mb_strtolower($email),
        'mail_driver' => 'log',
        'mail_from'   => 'no-reply@' . (parse_url((string) ($args['site-url'] ?? 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    ];

    $result = tb_install($db, $site, [
        'name'     => (string) ($args['name'] ?? 'Administrator'),
        'email'    => mb_strtolower($email),
        'password' => $pass,
    ], isset($args['demo']), isset($args['create-db']));

    foreach ($result['steps'] as $s) {
        echo "  ✓ $s\n";
    }
    if (!$result['ok']) {
        fwrite(STDERR, "\n✗ " . $result['error'] . "\n");
        exit(1);
    }

    echo "\nSetup complete.\n";
    echo "  Sign in at " . ($site['url'] !== '' ? $site['url'] : '(your site)') . "/admin/login\n";
    echo "  Then DELETE install.php\n";
    exit(0);
}

// =====================================================================
// Browser wizard
// =====================================================================
session_start();

$req      = tb_requirements();
$detected = [
    'origin'    => tb_detect_origin(),
    'base_path' => tb_detect_base_path(),
    'https'     => tb_is_https(),
    'timezone'  => tb_detect_timezone(),
];
$detectedUrl = $detected['origin'] . $detected['base_path'];

// The success screen is shown from the session immediately after a successful
// run, so it must be checked before the already-installed guard — otherwise the
// wizard finishes by telling you it cannot run, which is a confusing last word.
$justFinished = ($_GET['step'] ?? '') === 'done' && isset($_SESSION['tb_done']);

// An existing install cannot be set up again, but it can be upgraded. The
// wizard offers that instead of refusing outright — the database needs to
// gain new tables and columns after an update, and asking people to find a
// command line for it is how a site ends up half-migrated.
$upgradeMode = !$justFinished && tb_already_installed();

if ($upgradeMode) {
    $cfg  = require TB_CONFIG;
    $conn = tb_connect($cfg['db'] ?? []);
    $pdo  = $conn['ok'] ? $conn['pdo'] : null;

    $uErrors  = [];
    $uSteps   = [];
    $uDone    = false;
    $uLocked  = null;
    $uEmail   = trim((string) ($_POST['upgrade_email'] ?? ''));
    $uAuthed  = false;
    $uName    = '';
    $uPlan    = null;

    if ($pdo instanceof PDO) {
        require_once TB_ROOT . '/includes/Core/Migrator.php';
        $migrator = new Techbiss\Core\Migrator($pdo, TB_ROOT . '/database/schema.sql');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
        $action = (string) ($_POST['action'] ?? '');
        $auth   = tb_verify_admin($pdo, mb_strtolower($uEmail), (string) ($_POST['upgrade_password'] ?? ''));

        if (!$auth['ok']) {
            $uErrors['auth'] = $auth['error'];
        } else {
            $uAuthed = true;
            $uName   = $auth['name'];

            if ($action === 'lock') {
                $uLocked = tb_lock_installer();
                $uDone   = true;
            } elseif ($action === 'upgrade') {
                // A new administrator is optional: most upgrades keep the ones
                // that are already there.
                $addAdmin   = isset($_POST['add_admin']);
                $newName    = trim((string) ($_POST['new_admin_name'] ?? ''));
                $newEmail   = mb_strtolower(trim((string) ($_POST['new_admin_email'] ?? '')));
                $newPass    = (string) ($_POST['new_admin_password'] ?? '');

                if ($addAdmin) {
                    if ($newName === '') {
                        $uErrors['new_admin_name'] = 'Enter a name for the new administrator.';
                    }
                    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                        $uErrors['new_admin_email'] = 'Enter a valid email address.';
                    }
                    if (strlen($newPass) < 10) {
                        $uErrors['new_admin_password'] = 'Use at least 10 characters.';
                    } elseif (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
                        $uErrors['new_admin_password'] = 'Include both letters and numbers.';
                    }
                }

                if ($uErrors === []) {
                    try {
                        foreach ($migrator->apply() as $line) {
                            $uSteps[] = $line;
                        }
                        $copy = $migrator->refreshCopy();
                        if ($copy['rows'] > 0) {
                            $uSteps[] = 'Updated ' . $copy['rows'] . ' piece' . ($copy['rows'] === 1 ? '' : 's')
                                . ' of seeded wording (anything you had edited yourself was left alone)';
                        }
                    } catch (Throwable $e) {
                        $uErrors['upgrade'] = $e->getMessage();
                    }

                    if ($uErrors === [] && $addAdmin) {
                        $added = tb_add_admin($pdo, $newName, $newEmail, $newPass);
                        if ($added['ok']) {
                            $uSteps[] = 'Created the administrator ' . $newEmail;
                        } else {
                            $uErrors['new_admin_email'] = $added['error'];
                        }
                    }

                    if ($uErrors === []) {
                        tb_clear_cache();
                        $uSteps[] = 'Cleared the cache';
                        if ($uSteps === ['Cleared the cache']) {
                            $uSteps = ['The database was already up to date — nothing needed changing'];
                        }
                        $uDone = true;
                    }
                }
            }
        }
    }

    if ($pdo instanceof PDO && !$uDone && $uErrors === []) {
        try {
            $pending = $migrator->pending();
            $plan    = $migrator->refreshCopy(true);
            $uPlan   = [
                'tables'  => count($pending['tables']),
                'columns' => count($pending['columns']),
                'indexes' => count($pending['indexes']),
                'data'    => count($pending['data']),
                'copy'    => $plan['rows'],
            ];
        } catch (Throwable $e) {
            $uErrors['plan'] = $e->getMessage();
        }
    }

    $installedUrl = $detectedUrl . '/admin/login';
    $eh = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    require __DIR__ . '/pages/partials/_installer-upgrade.php';
    exit;
}

$step   = max(1, min(3, (int) ($_GET['step'] ?? 1)));
$errors = [];
$result = null;
$post   = static fn (string $k, string $d = ''): string => trim((string) ($_POST[$k] ?? $_SESSION['tb_setup'][$k] ?? $d));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    // Keep everything entered so far so the wizard can be walked backwards.
    foreach ($_POST as $k => $v) {
        if (is_string($v) && $k !== 'action' && $k !== 'admin_password' && $k !== 'admin_password_confirm') {
            $_SESSION['tb_setup'][$k] = $v;
        }
    }

    if ($action === 'test-db' || $action === 'install') {
        $db = [
            'host'   => $post('db_host', '127.0.0.1') ?: '127.0.0.1',
            'port'   => (int) ($post('db_port', '3306') ?: 3306),
            'name'   => $post('db_name'),
            'user'   => $post('db_user'),
            'pass'   => (string) ($_POST['db_pass'] ?? $_SESSION['tb_setup']['db_pass'] ?? ''),
            'socket' => $post('db_socket'),
        ];
        $_SESSION['tb_setup']['db_pass'] = $db['pass'];

        if ($db['name'] === '') {
            $errors['db_name'] = 'Enter the database name.';
        }
        if ($db['user'] === '') {
            $errors['db_user'] = 'Enter the database username.';
        }

        if ($errors === []) {
            $conn = tb_connect($db, isset($_POST['create_db']));
            if (!$conn['ok']) {
                $errors['db'] = $conn['error'];
            } elseif ($action === 'test-db') {
                $_SESSION['tb_setup']['db_ok'] = true;
                header('Location: ?step=3');
                exit;
            }
        }
        if ($errors !== []) {
            $step = 2;
        }
    }

    if ($action === 'install' && $errors === []) {
        $siteName = $post('site_name', 'TECHBISS') ?: 'TECHBISS';
        $siteUrl  = rtrim($post('site_url', $detectedUrl), '/');
        $adminName  = $post('admin_name');
        $adminEmail = mb_strtolower($post('admin_email'));
        $pw   = (string) ($_POST['admin_password'] ?? '');
        $pw2  = (string) ($_POST['admin_password_confirm'] ?? '');

        if ($adminName === '') {
            $errors['admin_name'] = 'Enter your name.';
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Enter a valid email address.';
        }
        if (strlen($pw) < 10) {
            $errors['admin_password'] = 'Use at least 10 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors['admin_password'] = 'Include both letters and numbers.';
        } elseif ($pw !== $pw2) {
            $errors['admin_password_confirm'] = 'The two passwords do not match.';
        }
        if ($siteUrl !== '' && !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'That does not look like a valid URL.';
        }

        if ($errors === []) {
            $host = parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost';
            $site = [
                'url'         => $siteUrl,
                'base_path'   => (string) (parse_url($siteUrl, PHP_URL_PATH) ?: ''),
                'timezone'    => $detected['timezone'],
                'https'       => str_starts_with($siteUrl, 'https://'),
                'name'        => $siteName,
                'email'       => $adminEmail,
                'mail_driver' => 'log',
                'mail_from'   => 'no-reply@' . $host,
            ];
            $site['base_path'] = rtrim($site['base_path'], '/');

            $result = tb_install($db, $site, [
                'name' => $adminName, 'email' => $adminEmail, 'password' => $pw,
            ], isset($_POST['load_demo']), isset($_POST['create_db']));

            if ($result['ok']) {
                unset($_SESSION['tb_setup']);
                $_SESSION['tb_done'] = ['url' => $siteUrl !== '' ? $siteUrl : $detectedUrl, 'steps' => $result['steps']];
                header('Location: ?step=done');
                exit;
            }
            $errors['install'] = $result['error'];
            $step = 3;
        } else {
            $step = 3;
        }
    }
}

if ($justFinished) {
    $step = 4;
    $done = $_SESSION['tb_done'];
    // Shown once; a refresh then correctly reports that setup is complete.
    unset($_SESSION['tb_done']);
}
if ($step === 3 && empty($_SESSION['tb_setup']['db_ok']) && $errors === []) {
    $step = 2;
}
if ($step > 1 && !$req['ok']) {
    $step = 1;
}

$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install TECHBISS</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/brand/favicon.svg">
    <link rel="shortcut icon" href="assets/images/brand/favicon.ico">
    <?php require __DIR__ . '/pages/partials/_installer-style.php'; ?>
</head>
<body>
<main class="wrap">
    <div class="card">
        <div class="brand"><img class="glyph" src="assets/images/brand/logo-mark.svg" width="32" height="32" alt=""><span class="name">TECHBISS</span></div>

        <?php if ($step < 4): ?>
        <ol class="steps" aria-label="Setup progress">
            <?php foreach (['Requirements', 'Database', 'Your site'] as $i => $label): ?>
            <li class="<?= $step === $i + 1 ? 'is-current' : ($step > $i + 1 ? 'is-done' : '') ?>">
                <span class="pip"><?= $step > $i + 1 ? '✓' : $i + 1 ?></span><?= $e($label) ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h1>Let's get TECHBISS running</h1>
            <p class="muted">This wizard writes your configuration, builds the database and creates your
               administrator account. Nothing needs editing by hand.</p>

            <div class="check-list">
                <div class="check <?= $req['php'] ? 'ok' : 'bad' ?>">
                    <span class="mark"><?= $req['php'] ? '✓' : '✗' ?></span>
                    <span>PHP 8.1 or newer <span class="muted">— found <?= $e(PHP_VERSION) ?></span></span>
                </div>
                <?php foreach ($req['required'] as $ext => $ok): ?>
                <div class="check <?= $ok ? 'ok' : 'bad' ?>">
                    <span class="mark"><?= $ok ? '✓' : '✗' ?></span>
                    <span>PHP extension <code><?= $e($ext) ?></code> <span class="muted">— required</span></span>
                </div>
                <?php endforeach; ?>
                <?php foreach ($req['optional'] as $ext => $info): ?>
                <div class="check <?= $info['loaded'] ? 'ok' : 'warn' ?>">
                    <span class="mark"><?= $info['loaded'] ? '✓' : '–' ?></span>
                    <span>PHP extension <code><?= $e($ext) ?></code>
                        <span class="muted">— optional, for <?= $e($info['why']) ?></span></span>
                </div>
                <?php endforeach; ?>
                <?php foreach ($req['writable'] as $dir => $ok): ?>
                <div class="check <?= $ok ? 'ok' : 'bad' ?>">
                    <span class="mark"><?= $ok ? '✓' : '✗' ?></span>
                    <span><code><?= $e($dir) ?>/</code> <span class="muted">— must be writable</span></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="detected">
                <div class="detected__title">Detected automatically</div>
                <dl>
                    <dt>Site address</dt><dd><?= $e($detectedUrl) ?></dd>
                    <dt>Sub-directory</dt><dd><?= $detected['base_path'] === '' ? 'none (domain root)' : $e($detected['base_path']) ?></dd>
                    <dt>Connection</dt><dd><?= $detected['https'] ? 'HTTPS — secure cookies will be enabled' : 'HTTP — enable HTTPS before going live' ?></dd>
                    <dt>Timezone</dt><dd><?= $e($detected['timezone']) ?></dd>
                </dl>
                <p class="muted small">You can change any of these on the last step.</p>
            </div>

            <?php if ($req['ok']): ?>
                <a class="btn btn--primary" href="?step=2">Continue to database setup →</a>
            <?php else: ?>
                <div class="alert alert--bad">
                    Fix the items marked ✗ above, then reload this page. Directories usually need
                    permission <code>755</code> (or <code>775</code> if PHP runs as a different user).
                </div>
                <a class="btn" href="?step=1">Re-check</a>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h1>Database</h1>
            <p class="muted">Enter the details from your hosting panel. The wizard tests the connection
               before writing anything.</p>

            <?php if (isset($errors['db'])): ?>
            <div class="alert alert--bad"><?= $e($errors['db']) ?></div>
            <?php endif; ?>

            <form method="post" action="?step=2">
                <input type="hidden" name="action" value="test-db">
                <div class="row">
                    <label class="field">
                        <span>Database host</span>
                        <input name="db_host" value="<?= $e($post('db_host', '127.0.0.1')) ?>" placeholder="127.0.0.1" autocomplete="off">
                        <em>Usually <code>localhost</code> or <code>127.0.0.1</code>.</em>
                    </label>
                    <label class="field field--sm">
                        <span>Port</span>
                        <input name="db_port" value="<?= $e($post('db_port', '3306')) ?>" inputmode="numeric" autocomplete="off">
                    </label>
                </div>

                <label class="field">
                    <span>Database name</span>
                    <input name="db_name" value="<?= $e($post('db_name', 'techbiss')) ?>" required autocomplete="off"
                           class="<?= isset($errors['db_name']) ? 'bad' : '' ?>">
                    <?php if (isset($errors['db_name'])): ?><em class="err"><?= $e($errors['db_name']) ?></em><?php endif; ?>
                </label>

                <div class="row">
                    <label class="field">
                        <span>Username</span>
                        <input name="db_user" value="<?= $e($post('db_user')) ?>" required autocomplete="off"
                               class="<?= isset($errors['db_user']) ? 'bad' : '' ?>">
                        <?php if (isset($errors['db_user'])): ?><em class="err"><?= $e($errors['db_user']) ?></em><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Password</span>
                        <input name="db_pass" type="password" value="<?= $e((string) ($_SESSION['tb_setup']['db_pass'] ?? '')) ?>" autocomplete="off">
                    </label>
                </div>

                <label class="check-inline">
                    <input type="checkbox" name="create_db" <?= isset($_POST['create_db']) ? 'checked' : '' ?>>
                    <span>Create the database for me if it does not exist
                        <em>Needs a user with CREATE rights — many shared hosts do not allow this.</em></span>
                </label>

                <details class="advanced">
                    <summary>Connecting through a socket instead?</summary>
                    <label class="field">
                        <span>Unix socket path</span>
                        <input name="db_socket" value="<?= $e($post('db_socket')) ?>" placeholder="/var/run/mysqld/mysqld.sock" autocomplete="off">
                        <em>Leave empty unless your host requires it. When set, host and port are ignored.</em>
                    </label>
                </details>

                <div class="actions">
                    <a class="btn btn--quiet" href="?step=1">← Back</a>
                    <button class="btn btn--primary" type="submit">Test connection &amp; continue →</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <h1>Your site and account</h1>
            <p class="muted">Almost there. The address below was detected from this page — change it only if
               visitors will use a different one.</p>

            <?php if (isset($errors['install'])): ?>
            <div class="alert alert--bad"><strong>Setup could not finish.</strong> <?= $e($errors['install']) ?></div>
            <?php endif; ?>

            <form method="post" action="?step=3">
                <input type="hidden" name="action" value="install">
                <?php foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_socket'] as $k): ?>
                <input type="hidden" name="<?= $e($k) ?>" value="<?= $e($post($k)) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="db_pass" value="<?= $e((string) ($_SESSION['tb_setup']['db_pass'] ?? '')) ?>">

                <label class="field">
                    <span>Company name</span>
                    <input name="site_name" value="<?= $e($post('site_name', 'TECHBISS')) ?>" required maxlength="120">
                    <em>Used in the header, page titles and emails. Changeable later in Settings.</em>
                </label>

                <label class="field">
                    <span>Site address <span class="tag">detected</span></span>
                    <input name="site_url" value="<?= $e($post('site_url', $detectedUrl)) ?>" required maxlength="255"
                           class="<?= isset($errors['site_url']) ? 'bad' : '' ?>">
                    <?php if (isset($errors['site_url'])): ?>
                        <em class="err"><?= $e($errors['site_url']) ?></em>
                    <?php else: ?>
                        <em>
                            <?= $detected['base_path'] === ''
                                ? 'Serving from the domain root.'
                                : 'Serving from the sub-directory <code>' . $e($detected['base_path']) . '</code>.' ?>
                            <?= $detected['https']
                                ? 'HTTPS detected — secure cookies will be switched on.'
                                : 'Running over HTTP; switch <code>cookie_secure</code> on in config once you have a certificate.' ?>
                        </em>
                    <?php endif; ?>
                </label>

                <hr>

                <div class="row">
                    <label class="field">
                        <span>Your name</span>
                        <input name="admin_name" value="<?= $e($post('admin_name')) ?>" required maxlength="120"
                               class="<?= isset($errors['admin_name']) ? 'bad' : '' ?>">
                        <?php if (isset($errors['admin_name'])): ?><em class="err"><?= $e($errors['admin_name']) ?></em><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Email address</span>
                        <input name="admin_email" type="email" value="<?= $e($post('admin_email')) ?>" required maxlength="190"
                               class="<?= isset($errors['admin_email']) ? 'bad' : '' ?>">
                        <?php if (isset($errors['admin_email'])): ?>
                            <em class="err"><?= $e($errors['admin_email']) ?></em>
                        <?php else: ?>
                            <em>You will sign in with this, and site enquiries go here.</em>
                        <?php endif; ?>
                    </label>
                </div>

                <div class="row">
                    <label class="field">
                        <span>Password</span>
                        <input name="admin_password" type="password" required minlength="10" autocomplete="new-password"
                               class="<?= isset($errors['admin_password']) ? 'bad' : '' ?>">
                        <?php if (isset($errors['admin_password'])): ?>
                            <em class="err"><?= $e($errors['admin_password']) ?></em>
                        <?php else: ?>
                            <em>At least 10 characters, with letters and numbers.</em>
                        <?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Confirm password</span>
                        <input name="admin_password_confirm" type="password" required autocomplete="new-password"
                               class="<?= isset($errors['admin_password_confirm']) ? 'bad' : '' ?>">
                        <?php if (isset($errors['admin_password_confirm'])): ?><em class="err"><?= $e($errors['admin_password_confirm']) ?></em><?php endif; ?>
                    </label>
                </div>

                <label class="check-inline">
                    <input type="checkbox" name="load_demo" <?= isset($_POST['load_demo']) ? 'checked' : '' ?>>
                    <span>Load the demo content
                        <em>Six fictional case studies, testimonials and articles so you can see a populated CMS.
                            Every name in it is invented and labelled as such. Remove it before launch with
                            <code>database/demo-content-remove.sql</code>.</em></span>
                </label>

                <div class="actions">
                    <a class="btn btn--quiet" href="?step=2">← Back</a>
                    <button class="btn btn--primary" type="submit">Install TECHBISS →</button>
                </div>
            </form>

        <?php else: ?>
            <div class="done-mark">✓</div>
            <h1>TECHBISS is ready</h1>
            <p class="muted">Setup finished successfully.</p>

            <ul class="done-list">
                <?php foreach (($done['steps'] ?? []) as $s): ?>
                <li><?= $e($s) ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="alert alert--warn">
                <strong>One last thing: delete <code>install.php</code>.</strong>
                It refuses to run again while an administrator exists, but there is no reason to leave it on the server.
            </div>

            <div class="actions actions--wide">
                <a class="btn btn--primary" href="<?= $e(($done['url'] ?? '') . '/admin/login') ?>">Sign in to the admin panel</a>
                <a class="btn" href="<?= $e($done['url'] ?? '/') ?>">View your site</a>
            </div>

            <details class="advanced">
                <summary>Before you go live</summary>
                <ul class="done-list">
                    <li>Serve every page over HTTPS, then set <code>cookie_secure =&gt; true</code> in <code>config/config.php</code></li>
                    <li>Add your logo, contact details and social links under <strong>Settings</strong></li>
                    <li>Replace the demo content with your own work, or remove it</li>
                    <li>Review the Privacy Policy and Terms pages with your legal advisor</li>
                    <li>Set the mail driver to <code>mail</code> in config once your server can send email</li>
                </ul>
            </details>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
