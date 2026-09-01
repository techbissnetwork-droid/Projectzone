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

/** Prefix a root-relative path with the auto-detected base URL. */
function url(string $path = ''): string
{
    return BASE_URL . $path;
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
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('X-XSS-Protection: 0');
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; "
        . "img-src 'self' data: blob:; media-src 'self' data: blob:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "script-src 'self' 'unsafe-inline' blob:; "
        . "frame-src 'self' blob:; child-src 'self' blob:; connect-src 'self'; "
        . "form-action 'self'; frame-ancestors 'self'"
    );
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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
        'live'        => ['Live',        ''],
        'for_sale'    => ['For Sale',    'FOR SALE'],
        'sold'        => ['Sold',        'SOLD'],
        'coming_soon' => ['Coming Soon', 'SOON'],
        'hidden'      => ['Hidden',      ''],
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

/** Projects for the main slider (live + for sale + coming soon; NOT sold, NOT hidden). */
function projects_slider(): array
{
    try {
        return db()->query(
            "SELECT * FROM projects WHERE COALESCE(status,'live') IN ('live','for_sale','coming_soon') ORDER BY sort_order, id"
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
