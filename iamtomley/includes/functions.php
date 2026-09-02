<?php
/** Shared helpers for public + admin. */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** HTML-escape. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** True once the install wizard has completed (lock file present). */
function is_installed(): bool
{
    return is_file(INSTALL_LOCK);
}

/**
 * Prefix a root-relative path with the auto-detected base URL, dropping the
 * .php where the server serves pretty URLs.
 */
function url(string $path = ''): string
{
    // Assets and directories have no extension to drop, and this keeps the
    // pre-install pages from touching the database.
    if (strpos($path, '.php') === false) {
        return BASE_URL . $path;
    }
    return BASE_URL . pretty_path($path);
}

/**
 * Pages that also exist as a folder with an index.php inside, so they can be
 * reached without .php on ANY server — no mod_rewrite, no .htaccess needed.
 * Only these are ever folded; anything else keeps its extension.
 */
function folder_pages(): array
{
    return [
        '/index.php', '/game.php', '/sitemap.php', '/robots.php',
        '/admin/index.php', '/admin/login.php', '/admin/logout.php',
        '/admin/settings.php', '/admin/projects.php', '/admin/stats.php',
        '/admin/games.php', '/admin/account.php',
        '/admin/detect-all.php', '/admin/detect-image.php',
    ];
}

/**
 * Which shape of address to write.
 *
 *   bare   — /admin/login      needs the server to rewrite; prettiest
 *   folder — /admin/login/     works everywhere, via the index.php folders
 *   php    — /admin/login.php  the admin has asked for it plainly
 *
 * On "auto" the bare form is used once the server has proved it rewrites (a
 * request arrives with no .php in the address while the running script has
 * one), and the folder form until then — so the address bar is clean either
 * way, on any host.
 */
function url_style(): string
{
    static $style = null;
    if ($style !== null) {
        return $style;
    }

    $mode   = 'auto';
    $proven = false;
    try {
        $mode   = setting('clean_urls_mode', 'auto');
        $proven = setting('clean_urls', '') === '1';

        if (!$proven) {
            // Proof: this request's address has no .php while the script does,
            // which only happens when the server rewrote it for us.
            $uri    = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $asked  = basename($uri);
            if ($script !== '' && str_ends_with($script, '.php')
                && $asked !== '' && $asked . '.php' === $script) {
                set_setting('clean_urls', '1');
                $proven = true;
            }
        }
    } catch (Throwable $e) {
        // No database yet; the folder form needs none, so carry on.
    }

    if ($mode === 'off') {
        $style = 'php';
        return $style;
    }

    // "Always" is a preference, not a promise. The short form is only ever
    // written once the server has actually shown it rewrites — otherwise every
    // link on the site would 404 and there would be no way back in.
    $style = $proven ? 'bare' : 'folder';
    return $style;
}

/** True when addresses are written without .php (either clean shape). */
function clean_urls(): bool
{
    return url_style() !== 'php';
}

/** "/admin/login.php" → "/admin/login" or "/admin/login/", per url_style(). */
function pretty_path(string $path): string
{
    $style = url_style();
    if ($style === 'php') {
        return $path;
    }

    // Split the query/fragment off so only the path itself is rewritten.
    $tail = '';
    foreach (['#', '?'] as $mark) {
        $at = strpos($path, $mark);
        if ($at !== false) {
            $tail = substr($path, $at) . $tail;
            $path = substr($path, 0, $at);
        }
    }

    if ($style === 'bare') {
        if (str_ends_with($path, '/index.php')) {
            $path = substr($path, 0, -strlen('index.php'));   // keep the slash
        } elseif (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        return $path . $tail;
    }

    // Folder form: only for pages that have a folder, and only when that
    // folder is actually present — an upload that skipped the new folders
    // falls back to .php instead of 404-ing the whole site.
    if (!in_array($path, folder_pages(), true)) {
        return $path . $tail;
    }
    $folded = str_ends_with($path, '/index.php')
        ? substr($path, 0, -strlen('index.php'))
        : substr($path, 0, -4) . '/';

    static $exists = [];
    if (!isset($exists[$folded])) {
        $exists[$folded] = is_file(APP_ROOT . $folded . 'index.php');
    }
    return ($exists[$folded] ? $folded : $path) . $tail;
}

/** Scheme + host of the current request (auto-detected, proxy-aware). */
function current_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    // Guard against header injection.
    $host = preg_replace('/[^A-Za-z0-9\.\-:]/', '', (string) $host);
    return $scheme . '://' . $host;
}

/** Absolute site URL for a root-relative path (auto-detected origin + base). */
function site_url(string $path = ''): string
{
    return current_origin() . BASE_URL . $path;
}

/** Canonical URL: use the admin-set value if provided, else auto-detect. */
function canonical_url(): string
{
    $c = trim(setting('seo_canonical'));
    return $c !== '' ? $c : site_url('/');
}

/**
 * Send hardening HTTP headers (CSP, anti-clickjacking, nosniff, …).
 * The CSP intentionally allows blob: frames + inline scripts so the
 * self-contained games keep working.
 *
 * $extraFrameSrc lets the public page name the exact origins of any games the
 * admin has linked to, so those — and only those — may be framed.
 */
function security_headers(array $extraFrameSrc = []): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('X-XSS-Protection: 0');

    $frames = "'self' blob:";
    foreach (array_unique($extraFrameSrc) as $origin) {
        // Only well-formed https?://host[:port] origins ever reach the header.
        if (preg_match('#^https?://[A-Za-z0-9\.\-]+(?::\d{1,5})?$#', (string) $origin)) {
            $frames .= ' ' . $origin;
        }
    }

    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; "
        . "img-src 'self' data: blob: https:; media-src 'self' data: blob:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "script-src 'self' 'unsafe-inline' blob:; "
        . "frame-src $frames; child-src $frames; connect-src 'self'; "
        . "form-action 'self'; frame-ancestors 'self'"
    );
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * URL for a CSS/JS/image file in the app, with the file's modification time
 * appended. Returning visitors get the new file the moment it changes instead
 * of a stale cached copy.
 */
function asset_url(string $path): string
{
    $file = APP_ROOT . $path;
    $stamp = is_file($file) ? (int) @filemtime($file) : 0;
    return url($path) . ($stamp > 0 ? '?v=' . $stamp : '');
}

/** The scheme://host[:port] part of a URL, or '' if it isn't a web address. */
function url_origin(string $url): string
{
    $p = parse_url(trim($url));
    if (!$p || empty($p['scheme']) || empty($p['host'])) { return ''; }
    $scheme = strtolower((string) $p['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') { return ''; }
    return $scheme . '://' . $p['host'] . (isset($p['port']) ? ':' . (int) $p['port'] : '');
}

/**
 * Render a self-contained "site is down / under construction" page and exit.
 * Inline CSS so it works even before installation (no DB / assets needed).
 */
function down_page(string $heading, string $sub, int $code = 503): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Retry-After: 3600');
        security_headers();
    }
    $h = e($heading); $s = e($sub);
    echo <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>{$h}</title><style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:grid;place-items:center;padding:1.5rem;background:#05060d;color:#eef0f8;
font-family:system-ui,-apple-system,'Segoe UI',sans-serif;overflow:hidden;text-align:center}
.bg{position:fixed;inset:0;z-index:-1;overflow:hidden}
.b{position:absolute;border-radius:50%;filter:blur(80px);opacity:.5}
.b1{width:46vw;height:46vw;top:-12vw;left:-8vw;background:radial-gradient(circle,rgba(0,255,179,.5),transparent 66%);animation:f1 22s ease-in-out infinite alternate}
.b2{width:40vw;height:40vw;top:10vh;right:-12vw;background:radial-gradient(circle,rgba(167,139,250,.42),transparent 66%);animation:f2 28s ease-in-out infinite alternate}
.b3{width:44vw;height:44vw;bottom:-14vw;left:28vw;background:radial-gradient(circle,rgba(255,60,172,.34),transparent 66%);animation:f3 26s ease-in-out infinite alternate}
@keyframes f1{to{transform:translate(12vw,10vh) scale(1.15)}}
@keyframes f2{to{transform:translate(-10vw,14vh) scale(1.1)}}
@keyframes f3{to{transform:translate(8vw,-12vh) scale(1.2)}}
.card{max-width:520px;padding:3rem 2rem;border-radius:24px;background:rgba(17,19,30,.6);
border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(20px);box-shadow:0 28px 64px rgba(3,4,10,.6)}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-bottom:1.25rem;
background:linear-gradient(135deg,#00ffb3,#00d4ff);box-shadow:0 0 16px rgba(0,255,179,.7);animation:p 2s ease-in-out infinite}
@keyframes p{0%,100%{opacity:1}50%{opacity:.3}}
h1{font-size:clamp(1.6rem,5vw,2.4rem);font-weight:800;letter-spacing:-.02em;margin-bottom:.75rem;line-height:1.1}
p{color:#868aa3;font-size:1.02rem;line-height:1.7}
@media(prefers-reduced-motion:reduce){.b{animation:none}.dot{animation:none}}
</style></head><body>
<div class="bg"><span class="b b1"></span><span class="b b2"></span><span class="b b3"></span></div>
<div class="card"><span class="dot"></span><h1>{$h}</h1><p>{$s}</p></div>
</body></html>
HTML;
    exit;
}

/** All settings as an associative array (cached per request). */
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT skey, svalue FROM settings") as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}

/** Single setting with fallback. */
function setting(string $key, string $default = ''): string
{
    $all = settings();
    return array_key_exists($key, $all) ? (string) $all[$key] : $default;
}

/**
 * Record that the site's content just changed. The sitemap publishes this as
 * the page's <lastmod>, so a crawler can tell there is something new without
 * re-reading the whole page.
 */
function touch_content(): void
{
    try {
        set_setting('content_updated', (string) time());
    } catch (Throwable $e) { /* never block a save over this */ }
}

/** When the content last changed, as a Unix timestamp (0 if never recorded). */
function content_updated_at(): int
{
    $t = (int) setting('content_updated', '0');
    if ($t > 0) { return $t; }
    // Nothing recorded yet — fall back to when the site itself was last built.
    $f = APP_ROOT . '/index.php';
    return is_file($f) ? (int) @filemtime($f) : 0;
}

/** Persist one setting (upsert). */
function set_setting(string $key, string $value): void
{
    $sql = DB_DRIVER === 'mysql'
        ? "INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)"
        : "INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue";
    db()->prepare($sql)->execute([$key, $value]);
}

/**
 * Project statuses. 'hidden' is never shown publicly; the others show with a
 * matching badge. Add more here (key => [label, badge-text]) and they're
 * available everywhere automatically.
 */
function project_statuses(): array
{
    return [
        'live'         => ['Live',         ''],
        'for_sale'     => ['For Sale',     'FOR SALE'],
        'not_for_sale' => ['Not For Sale', 'NOT FOR SALE'],
        'sold'         => ['Sold',         'SOLD'],
        'coming_soon'  => ['Coming Soon',  'SOON'],
        'hidden'       => ['Hidden',       ''],
    ];
}
function project_status_key(string $k): string
{
    return array_key_exists($k, project_statuses()) ? $k : 'live';
}

/** Publicly visible projects (everything except 'hidden'). Falls back safely
 *  if the status column hasn't been migrated in yet (never blanks the page). */
function projects_active(): array
{
    try {
        return db()->query(
            "SELECT * FROM projects WHERE COALESCE(status,'live') <> 'hidden' ORDER BY sort_order, id"
        )->fetchAll();
    } catch (Throwable $e) {
        return db()->query(
            "SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order, id"
        )->fetchAll();
    }
}

/** Projects for the main slider (everything still on show; NOT sold, NOT hidden). */
function projects_slider(): array
{
    try {
        return db()->query(
            "SELECT * FROM projects WHERE COALESCE(status,'live') IN ('live','for_sale','not_for_sale','coming_soon') ORDER BY sort_order, id"
        )->fetchAll();
    } catch (Throwable $e) {
        return db()->query(
            "SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order, id"
        )->fetchAll();
    }
}

/** Sold projects (for the dedicated Sold section). Empty if no status column. */
function projects_sold(): array
{
    try {
        return db()->query(
            "SELECT * FROM projects WHERE COALESCE(status,'live') = 'sold' ORDER BY sort_order, id"
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function games_active(): array
{
    return db()->query(
        "SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order, id"
    )->fetchAll();
}

/**
 * Where a game's playable code comes from:
 *  - builtin: one of the 20 shipped games, looked up by its code_ref in games-data.js
 *  - url:     any game on the web, shown in a frame
 *  - html:    a single-file HTML game pasted into the admin
 */
function game_sources(): array
{
    return [
        'builtin' => 'Built-in game',
        'url'     => 'Link to a game',
        'html'    => 'Pasted HTML game',
    ];
}

function game_source_key(string $k): string
{
    return array_key_exists($k, game_sources()) ? $k : 'builtin';
}

/**
 * Trim a value to what its database column can hold.
 *
 * SQLite ignores column widths, MySQL in its default strict mode rejects the
 * whole row — so a long title typed into the admin would save on one and error
 * on the other. Clipping here keeps both behaving the same way.
 */
function clip(string $value, int $max): string
{
    $value = trim($value);
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

/** Turn free text into a short url/attribute-safe key ("Retro Arcade" → "retro-arcade"). */
function slugify(string $s, int $max = 20): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return substr(trim($s, '-'), 0, $max);
}

/** The five shipped game categories, in the order they appear as filters. */
function builtin_game_categories(): array
{
    return ['arcade' => 'Arcade', 'puzzle' => 'Puzzle', 'action' => 'Action', 'sports' => 'Sports', 'casual' => 'Casual'];
}

/**
 * Every category to offer in the admin and use for the public filters: the five
 * built-ins plus any the admin has typed on a game of their own.
 */
function game_categories(): array
{
    $cats = builtin_game_categories();
    try {
        foreach (db()->query("SELECT DISTINCT cat FROM games") as $row) {
            $key = slugify((string) ($row['cat'] ?? ''));
            if ($key !== '' && !isset($cats[$key])) {
                $cats[$key] = ucwords(str_replace('-', ' ', $key));
            }
        }
    } catch (Throwable $e) { /* fall back to the built-ins */ }
    return $cats;
}

/** Display label for a category key. */
function game_cat_label(string $key): string
{
    $cats = game_categories();
    return $cats[$key] ?? ucwords(str_replace('-', ' ', $key));
}

/**
 * Resolve a stored media path for display: a root-relative path gets the
 * base-URL prefix, a full URL is left alone, and an empty value yields ''.
 */
function media(string $p): string
{
    $p = trim($p);
    if ($p === '') { return ''; }
    return $p[0] === '/' ? url($p) : $p;
}

function stats_all(): array
{
    return db()->query("SELECT * FROM stats ORDER BY sort_order, id")->fetchAll();
}

/** Chunk a flat array into groups of $size. */
function chunk_slides(array $items, int $size = 4): array
{
    return array_chunk($items, $size);
}

/**
 * Animated background themes (all dark + glass; the visitor can switch and the
 * choice is remembered in their browser). key => [label, accent-swatch css].
 */
function bg_themes(): array
{
    return [
        'aurora'    => ['Aurora',      'linear-gradient(135deg,#00ffb3,#a78bfa,#ff3cac)'],
        'blobs'     => ['Lava Blobs',  'linear-gradient(135deg,#00ffb3,#00d4ff)'],
        'mesh'      => ['Mesh Flow',   'linear-gradient(135deg,#a78bfa,#00d4ff,#ff3cac)'],
        'waves'     => ['Waves',       'linear-gradient(135deg,#00d4ff,#3cb4ff,#00ffb3)'],
        'particles' => ['Particles',   'linear-gradient(135deg,#00ffb3,#3cb4ff)'],
        'grid'      => ['Neon Grid',   'linear-gradient(135deg,#ff3cac,#a78bfa)'],
        'nebula'    => ['Nebula',      'linear-gradient(135deg,#a78bfa,#ff3cac,#00d4ff)'],
        'rain'      => ['Matrix Rain', 'linear-gradient(135deg,#00ffb3,#2ee6a6)'],
        'orbits'    => ['Orbits',      'linear-gradient(135deg,#00d4ff,#a78bfa)'],
        'starfield' => ['Starfield',   'linear-gradient(135deg,#c3c6d6,#00d4ff)'],
        'sunset'    => ['Sunset',      'linear-gradient(135deg,#ffc83c,#ff3cac,#a78bfa)'],
    ];
}

/** Validate a background theme key, falling back to aurora. */
function bg_theme_key(string $k): string
{
    return array_key_exists($k, bg_themes()) ? $k : 'aurora';
}
