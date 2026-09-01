<?php
/**
 * Database bootstrap: PDO connection, schema migration, first-run seed.
 * Works with SQLite (default) or MySQL — same code path via PDO.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } else {
        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }

    migrate($pdo);
    return $pdo;
}

/** Small helper: auto-increment column definition per driver. */
function pk(): string
{
    return DB_DRIVER === 'mysql'
        ? 'INT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
}

/** Per-table options — force utf8mb4 on MySQL so emoji work; nothing on SQLite. */
function table_opts(): string
{
    return DB_DRIVER === 'mysql'
        ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : '';
}

/** Drop all app tables (used by the installer's "fresh install" option). */
function drop_app_tables(PDO $pdo): void
{
    foreach (['login_attempts', 'games', 'projects', 'stats', 'users', 'settings'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS $t");
    }
}

/** Does this database already contain seeded app data? */
function has_app_data(PDO $pdo): bool
{
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Add a column to an existing table if it isn't already present (SQLite/MySQL). */
function ensure_column(PDO $pdo, string $table, string $col, string $definition): void
{
    $has = false;
    try {
        if (DB_DRIVER === 'mysql') {
            $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute([$col]);
            $has = (bool) $st->fetch();
        } else {
            foreach ($pdo->query("PRAGMA table_info($table)") as $c) {
                if (($c['name'] ?? '') === $col) { $has = true; break; }
            }
        }
        if (!$has) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $col $definition");
            // Backfill: previously-hidden projects become status 'hidden'.
            if ($table === 'projects' && $col === 'status') {
                $pdo->exec("UPDATE projects SET status='hidden' WHERE is_active=0");
            }
        }
    } catch (Throwable $e) { /* non-fatal */ }
}

function migrate(PDO $pdo): void
{
    $pk = pk();

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey   VARCHAR(64) PRIMARY KEY,
        svalue TEXT
    )" . table_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        username      VARCHAR(64) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL
    )" . table_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $pk,
        ip VARCHAR(64) NOT NULL,
        at INTEGER NOT NULL
    )" . table_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS stats (
        id $pk,
        value      VARCHAR(32) NOT NULL DEFAULT '',
        label      VARCHAR(64) NOT NULL DEFAULT '',
        target     VARCHAR(16) NOT NULL DEFAULT '',
        suffix     VARCHAR(8)  NOT NULL DEFAULT '',
        sort_order INTEGER     NOT NULL DEFAULT 0
    )" . table_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id $pk,
        title       VARCHAR(120) NOT NULL DEFAULT '',
        tag         VARCHAR(60)  NOT NULL DEFAULT '',
        tag_style   VARCHAR(16)  NOT NULL DEFAULT '',
        description TEXT,
        url         VARCHAR(255) NOT NULL DEFAULT '#',
        link_label  VARCHAR(40)  NOT NULL DEFAULT 'Visit Site',
        sort_order  INTEGER      NOT NULL DEFAULT 0,
        is_active   INTEGER      NOT NULL DEFAULT 1,
        status      VARCHAR(20)  NOT NULL DEFAULT 'live',
        price       VARCHAR(40)  NOT NULL DEFAULT '',
        image       VARCHAR(255) NOT NULL DEFAULT ''
    )" . table_opts());
    // Upgrade older installs that predate these columns.
    ensure_column($pdo, 'projects', 'status', "VARCHAR(20) NOT NULL DEFAULT 'live'");
    ensure_column($pdo, 'projects', 'price', "VARCHAR(40) NOT NULL DEFAULT ''");
    ensure_column($pdo, 'projects', 'image', "VARCHAR(255) NOT NULL DEFAULT ''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id $pk,
        code_ref   INTEGER      NOT NULL DEFAULT 0,
        title      VARCHAR(80)  NOT NULL DEFAULT '',
        cat        VARCHAR(32)  NOT NULL DEFAULT 'arcade',
        emoji      VARCHAR(16)  NOT NULL DEFAULT '',
        sort_order INTEGER      NOT NULL DEFAULT 0,
        is_active  INTEGER      NOT NULL DEFAULT 1,
        source     VARCHAR(10)  NOT NULL DEFAULT 'builtin',
        embed_url  VARCHAR(255) NOT NULL DEFAULT '',
        html_code  TEXT,
        cover      VARCHAR(255) NOT NULL DEFAULT ''
    )" . table_opts());
    // Columns added by the "add your own games" upgrade.
    ensure_column($pdo, 'games', 'source',    "VARCHAR(10) NOT NULL DEFAULT 'builtin'");
    ensure_column($pdo, 'games', 'embed_url', "VARCHAR(255) NOT NULL DEFAULT ''");
    ensure_column($pdo, 'games', 'html_code', "TEXT");
    ensure_column($pdo, 'games', 'cover',     "VARCHAR(255) NOT NULL DEFAULT ''");

    seed($pdo);
}

/**
 * The editable content copy shipped with the site (hero, contact, footer, SEO).
 * These are the keys the admin "Load latest premium copy" button applies, so
 * they're kept separate from the system prefs (theme, bg, maintenance…).
 */
function starter_content(): array
{
    return [
        'hero_eyebrow'     => 'Chronically building',
        'hero_title_1'     => 'From my head',
        'hero_title_2'     => 'to the internet.',
        'hero_sub'         => "I build the stuff I wish existed — SMM panels, marketplaces, DEXs, dating apps and games — then I put it online. Some of it's for sale.",
        'hero_cta1_label'  => 'Explore Projects',
        'hero_cta1_url'    => '#projects',
        'hero_cta2_label'  => 'Play the Games',
        'hero_cta2_url'    => '#games',
        'hero_badge_label' => 'Status',
        'hero_badge_value' => 'Building 🔨',

        'contact_title'    => "Let's build something\nworth talking about.",
        'contact_sub'      => "Got a wild idea, a half-finished dream, or a project that keeps you up at night?\nSend it over — I read every message.",
        'contact_email'    => 'hello@iamtomley.com',

        'footer_text'      => '© 2026 iamtomley — built with curiosity, caffeine & zero deadlines',

        'seo_title'        => 'iamtomley — Developer, Builder & Game Maker',
        'seo_description'  => 'A developer who turns random ideas into real products — tools, web apps and 20+ instantly-playable browser games. Built for the fun of it, shipped for real.',
    ];
}

/** The default stat tiles (value, label, target, suffix, sort). */
function starter_stats(): array
{
    return [
        ['8',    'Live Projects', '8',   '',  1],
        ['20+',  'Playable Games','20',  '+', 2],
        ['∞',    'Ideas Shipped', '',    '',  3],
        ['100%', 'Passion',       '100', '%', 4],
    ];
}

/** The default project cards. */
function starter_projects(): array
{
    return [
        ['DATAWASTER', 'Featured', '', 'Intentionally burns internet data to stress-test bandwidth caps and expose ISP throttling in real time.', 'https://datawaster.net', 'Visit Site', 1],
        ['MYSPEEDCHECKER', 'Tool', 'pink', 'Real-time internet speed testing — download, upload and latency in one clean, no-nonsense dashboard.', 'https://myspeedchecker.net', 'Visit Site', 2],
        ['SAINOSTUDIO', 'Photo / Videography', 'violet', 'Weddings, birthdays, engagements — every milestone worth keeping. Shot on location, edited by hand, delivered like a story.', 'https://sainostudio.com', 'Visit Site', 3],
        ['VRXZONE', 'GameZone', '', 'Reality ends here. Full-body VR combat, heists and horror on Meta Quest 2 & 3 — untethered, in a space built for it.', 'https://vrxzone.com', 'Visit Site', 4],
        ['CATCH FRUITS PRO', 'Game', 'pink', 'A deceptively simple browser game. Catch the fruit, chase the high score, lose an afternoon. Pure, unfiltered fun.', 'https://www.iamtomley.com/games/catchfruitspro/', 'Play Now', 5],
        ['MULTITOKENSENDER', 'Tool', 'pink', 'Send bulk token airdrops to unlimited wallets in a single, gas-optimised transaction — fast and cheap.', '/', 'Visit Site', 6],
        ['GLOBALSMMSERVICE', 'Tool', 'pink', 'A complete social-media marketing engine — followers, engagement and reach across every major platform from one dashboard.', 'https://globalsmmservice.com', 'Visit Site', 7],
        ['ANYTOKENSWAP', 'Tool', 'pink', 'A non-custodial DeFi exchange for swapping any token to any token — instant routing and best-price aggregation.', '/', 'Visit Site', 8],
    ];
}

/** Seed initial content once (idempotent). */
function seed(PDO $pdo): void
{
    // Only seed if empty
    $seeded = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($seeded > 0) {
        return;
    }

    // ── Settings ──────────────────────────────────────────────────
    $settings = array_merge([
        'brand_name'       => 'iamtomley',
        'theme_default'    => 'dark',
        'bg_theme'         => 'aurora',
        'backdrop_on'      => '1',
        'backdrop_image'   => '/bg.jpg',
        'maintenance_mode' => '0',
        'hero_photo'       => '/bg.jpg',
        'projects_coming_soon' => '1',   // fill the last slide's empty spots with "coming soon"
        'seo_canonical'    => '',         // empty → auto-detected from the request URL
    ], starter_content());
    $st = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $st->execute([$k, $v]);
    }

    // ── Admin user (default: admin / admin) ───────────────────────
    $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)")
        ->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);

    // ── Stats ─────────────────────────────────────────────────────
    $st = $pdo->prepare("INSERT INTO stats (value,label,target,suffix,sort_order) VALUES (?,?,?,?,?)");
    foreach (starter_stats() as $r) {
        $st->execute($r);
    }

    // ── Projects (the 8 real cards) ───────────────────────────────
    $st = $pdo->prepare("INSERT INTO projects (title,tag,tag_style,description,url,link_label,sort_order,is_active,status) VALUES (?,?,?,?,?,?,?,1,'live')");
    foreach (starter_projects() as $r) {
        $st->execute($r);
    }

    // ── Games (20, mapped to gHTML() code refs 1..20) ─────────────
    $games = [
        [1,'Snake','arcade','🐍'],[2,'Tetris','arcade','🟦'],[3,'Flappy Bird','arcade','🐦'],
        [4,'Brick Breaker','arcade','🧱'],[5,'Dino Run','arcade','🦕'],[6,'Space Shooter','action','🚀'],
        [7,'Car Dodge','action','🚗'],[8,'Pong','sports','🏓'],[9,'2048','puzzle','🔢'],
        [10,'Memory Match','puzzle','🧩'],[11,'Math Blitz','puzzle','➕'],[12,'Word Guess','puzzle','🟩'],
        [13,'Number Hunt','puzzle','🔢'],[14,'Trivia Quiz','casual','🎯'],[15,'Color Match','casual','🎨'],
        [16,'Reaction Time','casual','⚡'],[17,'Typing Speed','casual','⌨️'],[18,'Ping Pong','sports','🎾'],
        [19,'Asteroid','action','☄️'],[20,'Bubble Pop','casual','🫧'],
    ];
    $st = $pdo->prepare("INSERT INTO games (code_ref,title,cat,emoji,sort_order,is_active) VALUES (?,?,?,?,?,1)");
    foreach ($games as $i => $g) {
        $st->execute([$g[0], $g[1], $g[2], $g[3], $i + 1]);
    }
}
