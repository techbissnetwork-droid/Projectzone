<?php
require_once __DIR__ . '/mailer.php';

define('CONFIG_PATH', __DIR__ . '/../config.php');
define('INSTALL_LOCK_PATH', __DIR__ . '/../install.lock');

// Sign-in code limits. Per-code attempts (5) bound one guess session; these
// two bound the account overall, so requesting new codes can't be used to
// buy unlimited guesses.
const OTP_MAX_PER_HOUR = 6;
const OTP_MAX_FAILURES_PER_HOUR = 12;

// Staff login throttle: attempts allowed per email and per IP per window.
const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_WINDOW_SECONDS = 900;

/**
 * Cache-buster for assets/style.css and assets/app.js.
 *
 * This was filemtime(), which looks right but fails in practice: FTP and
 * many deploy tools preserve the original modification time, so the ?v=
 * value doesn't change on upload and browsers keep serving the old
 * stylesheet against freshly-updated PHP. That produced two separate
 * "it looks broken on my phone" reports — new markup, stale CSS.
 *
 * Bump this string whenever assets/ changes.
 */
const ASSET_VERSION = '2026.09.05.4';

if (file_exists(CONFIG_PATH)) {
    require_once CONFIG_PATH;
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) {
        return true;
    }
    // Behind a load balancer / CDN the origin request is plain HTTP, so the
    // scheme the visitor actually used only survives in this header.
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        // Only set Secure when the request really is HTTPS: setting it on a
        // plain-HTTP dev server would make the browser drop the cookie and
        // no one could stay signed in.
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Sent from every entry point. Frame-blocking matters most for /admin/,
 * but there is no page here that benefits from being embedded elsewhere.
 * Set before any output so they survive on error paths too.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
send_security_headers();

/**
 * True when the connected database already holds this application's own
 * tables. This — not the presence of install.lock — is what "already
 * installed" really means: install.lock is listed in .gitignore, so any
 * deploy that pushes from git or re-uploads the repository arrives
 * without it. Deriving installed-state from the file alone re-opened the
 * installer (and its database-wiping branch) on live sites.
 *
 * Cached for the request; a false result is not cached, because the
 * installer creates these tables mid-request and must see that happen.
 */
function db_has_app_tables(?PDO $pdo = null): bool
{
    static $found = false;
    if ($found) {
        return true;
    }
    try {
        if ($pdo === null) {
            if (!defined('DB_HOST')) {
                return false;
            }
            // A short-lived connection of its own, rather than db(): db()
            // exits the request with a JSON error when the database is
            // unreachable, and this must be answerable with a plain false.
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        }
        // `staff` is created by the very first migration and is never
        // dropped by an update, so its presence means a real install.
        $found = (bool)$pdo->query("SHOW TABLES LIKE 'staff'")->fetch();
    } catch (Throwable $e) {
        return false;
    }
    return $found;
}

function is_installed(): bool
{
    if (!file_exists(CONFIG_PATH) || !defined('DB_HOST')) {
        return false;
    }
    if (file_exists(INSTALL_LOCK_PATH)) {
        return true;
    }
    // Lock file missing but the schema is there: a redeploy dropped the
    // file. Treat the site as installed and rewrite the lock rather than
    // sending every visitor back into the installer.
    if (db_has_app_tables()) {
        @file_put_contents(INSTALL_LOCK_PATH, date('c'));
        return true;
    }
    return false;
}

/**
 * Call at the top of any real entry point (index.php, admin/*.php, api/*.php).
 * $installUrl is the relative path to the installer from that file's location.
 */
function require_installed(string $installUrl = 'install/'): void
{
    if (!is_installed()) {
        header('Location: ' . $installUrl);
        exit;
    }
}

/** For api/*.php: JSON error instead of redirecting a fetch() call into an HTML page. */
function require_installed_api(): void
{
    if (!is_installed()) {
        send_json(['error' => 'Site is not set up yet. Visit /install/ in a browser to finish setup.'], 503);
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!defined('DB_HOST')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Site is not configured yet. Visit /install/ to set it up.']);
        exit;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => (defined('APP_DEBUG') && APP_DEBUG)
            ? ('Database connection failed: ' . $e->getMessage())
            : 'Database connection failed. Check config.php and that the installer has been run.']);
        exit;
    }
    return $pdo;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function send_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function current_customer(): ?array
{
    if (empty($_SESSION['customer_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email FROM customers WHERE id = ?');
    $stmt->execute([$_SESSION['customer_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Customers have no password — they sign in with an emailed one-time
 * code or a magic link, and the same mechanism backs the two-step
 * (verify old email, then verify new email) email-change flow on the
 * account page. otp_issue() throws if one was already issued for this
 * customer+purpose in the last 60 seconds, as a simple resend limiter.
 */
function otp_issue(int $customerId, string $purpose, ?string $newEmail = null, int $ttlMinutes = 10): array
{
    $pdo = db();

    // Expired and spent codes are never read again; without this the table
    // grows without bound and every verify scans more rows than the last.
    $pdo->exec('DELETE FROM otp_codes WHERE expires_at < (NOW() - INTERVAL 1 DAY)');

    $recent = $pdo->prepare(
        'SELECT id FROM otp_codes WHERE customer_id = ? AND purpose = ? AND used_at IS NULL AND created_at > (NOW() - INTERVAL 60 SECOND)'
    );
    $recent->execute([$customerId, $purpose]);
    if ($recent->fetch()) {
        throw new RuntimeException('Please wait a moment before requesting another code.');
    }

    // Per-code attempt limits alone don't stop brute force: an attacker can
    // simply request a fresh code every 60s and spend 5 more guesses on it,
    // forever. Cap how many codes one account can be issued per hour so the
    // guess rate is bounded overall, not just per code.
    $burst = $pdo->prepare(
        'SELECT COUNT(*) FROM otp_codes WHERE customer_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $burst->execute([$customerId]);
    if ((int)$burst->fetchColumn() >= OTP_MAX_PER_HOUR) {
        throw new RuntimeException('Too many codes requested for this account. Please try again later.');
    }
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'INSERT INTO otp_codes (customer_id, purpose, code_hash, token_hash, new_email, expires_at) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $customerId, $purpose, hash('sha256', $code), hash('sha256', $token), $newEmail,
        date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
    ]);
    return ['id' => (int)$pdo->lastInsertId(), 'code' => $code, 'token' => $token];
}

function otp_verify_code(int $customerId, string $purpose, string $code): ?array
{
    $pdo = db();

    // A failed guess burns an attempt on whichever code is current; once an
    // account has burned this many across all its recent codes, stop
    // answering at all until they age out, so requesting a fresh code no
    // longer buys a fresh allowance.
    $recentFails = $pdo->prepare(
        'SELECT COALESCE(SUM(attempts),0) FROM otp_codes WHERE customer_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $recentFails->execute([$customerId]);
    if ((int)$recentFails->fetchColumn() >= OTP_MAX_FAILURES_PER_HOUR) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM otp_codes WHERE customer_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$customerId, $purpose]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['attempts'] >= 5) {
        return null;
    }
    if (!hash_equals($row['code_hash'], hash('sha256', $code))) {
        $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        return null;
    }
    $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return $row;
}

function otp_verify_token(string $purpose, string $token): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM otp_codes WHERE purpose = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$purpose, hash('sha256', $token)]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return $row;
}

/**
 * Fixed-window counter keyed on an arbitrary string, backed by a table so
 * it survives across PHP workers (an in-process counter would reset on
 * every request). Returns false once $limit is reached inside $window
 * seconds. Fails open if the table is missing — a rate limiter must never
 * be the reason a site stops working.
 */
function rate_limit_hit(string $key, int $limit, int $windowSeconds): bool
{
    try {
        $pdo = db();
        $pdo->prepare('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL ? SECOND)')
            ->execute([$windowSeconds * 4]);

        $hash = hash('sha256', $key);
        $stmt = $pdo->prepare('SELECT hits, UNIX_TIMESTAMP(window_start) AS started FROM rate_limits WHERE id = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || (time() - (int)$row['started']) >= $windowSeconds) {
            $pdo->prepare(
                'INSERT INTO rate_limits (id, hits, window_start) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()'
            )->execute([$hash]);
            return true;
        }
        if ((int)$row['hits'] >= $limit) {
            return false;
        }
        $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?')->execute([$hash]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function current_staff(): ?array
{
    if (empty($_SESSION['staff_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, role, permissions, is_owner, marketing_daily_goal, marketing_daily_cap FROM staff WHERE id = ?');
    $stmt->execute([$_SESSION['staff_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_staff(): array
{
    $staff = current_staff();
    if (!$staff) {
        header('Location: login.php');
        exit;
    }
    return $staff;
}

/**
 * NULL return = full access to every admin section. A non-null array lists
 * the specific section keys (e.g. "businesses", "settings" — the admin
 * page filename without ".php") this staff member is allowed into.
 */
function staff_permissions(array $staff): ?array
{
    if (!empty($staff['is_owner']) || ($staff['permissions'] ?? null) === null || $staff['permissions'] === '') {
        return null;
    }
    $decoded = json_decode((string)$staff['permissions'], true);
    return is_array($decoded) ? $decoded : [];
}

function staff_can(array $staff, string $page): bool
{
    $perms = staff_permissions($staff);
    if ($perms === null) {
        return true;
    }
    if (isset(PAGE_PERMISSION_ALIASES[$page])) {
        foreach (PAGE_PERMISSION_ALIASES[$page] as $key) {
            if (in_array($key, $perms, true)) {
                return true;
            }
        }
        return false;
    }
    $key = pathinfo($page, PATHINFO_FILENAME);
    return $key === 'index' || in_array($key, $perms, true);
}

function staff_has_permission(array $staff, string $key): bool
{
    $perms = staff_permissions($staff);
    return $perms === null || in_array($key, $perms, true);
}

/**
 * Only the owner may hand out access. Without this, the "staff" section
 * was a superuser bit: anyone holding it could tick "Full access" on their
 * own account and unlock every other section, or reset a colleague's
 * password and sign in as them.
 */
function staff_is_owner(array $staff): bool
{
    return !empty($staff['is_owner']);
}

function require_staff_access(array $staff, string $page): void
{
    if (!staff_can($staff, $page)) {
        flash("You don't have access to that section.", 'error');
        header('Location: index.php');
        exit;
    }
}

/**
 * The JSON endpoints have no CSRF token of their own. SameSite=Lax on the
 * session cookie already blocks the classic cross-site form post, but it
 * is one setting and covers less than it looks (no protection if a browser
 * defaults differently, none for same-site subdomains). Checking that the
 * request actually came from this site is three lines and closes the gap.
 *
 * Requests with no Origin/Referer at all are allowed: same-origin GETs and
 * some privacy tools omit both, and the callers here are all POSTs already
 * gated on a session.
 */
function require_same_origin(): void
{
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $origin = (string)parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_SCHEME)
            . '://' . (string)parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $port = parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_PORT);
        if ($port) {
            $origin .= ':' . $port;
        }
    }
    if ($origin === '') {
        return;
    }

    $expected = [];
    if (defined('SITE_URL')) {
        $u = parse_url(SITE_URL);
        if (!empty($u['host'])) {
            $expected[] = ($u['scheme'] ?? 'https') . '://' . $u['host'] . (!empty($u['port']) ? ':' . $u['port'] : '');
        }
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = request_is_https() ? 'https' : 'http';
        $expected[] = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    foreach ($expected as $candidate) {
        if (strcasecmp($origin, $candidate) === 0) {
            return;
        }
    }
    send_json(['error' => 'Request blocked — it did not come from this site.'], 403);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(string $token): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function all_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $rows = db()->query('SELECT id, value FROM settings')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
    $cache = [];
    foreach ($rows as $r) {
        $cache[$r['id']] = $r['value'];
    }
    return $cache;
}

function get_setting(string $key, string $default = ''): string
{
    $all = all_settings();
    return $all[$key] ?? $default;
}

/**
 * Repeatable content sections (Services, Industries, Case Studies,
 * Pricing, FAQ, Team, Values) — each backed by its own table
 * (install/migrations/014_content_tables.php) so admin/content.php can
 * add/edit/delete individual items. Shaped to match exactly what
 * assets/app.js expects for each section (see Pages['/'], ['/about'],
 * ['/pricing'], etc.) so the frontend needs no changes when content
 * is added, edited or removed.
 */
function content_services_rows(): array
{
    $rows = db()->query('SELECT icon, name, blurb, bullets_json FROM content_services ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => [
        'icon' => $r['icon'], 'name' => $r['name'], 'blurb' => $r['blurb'],
        'bullets' => json_decode($r['bullets_json'], true) ?: [],
    ], $rows);
}

function content_industries_rows(): array
{
    $rows = db()->query('SELECT icon, name, out_json FROM content_industries ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => [
        'icon' => $r['icon'], 'name' => $r['name'], 'out' => json_decode($r['out_json'], true) ?: [],
    ], $rows);
}

function content_case_studies_rows(): array
{
    $rows = db()->query('SELECT sector, icon, client, stat, stat_label, quote, body FROM content_case_studies ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => [
        'sector' => $r['sector'], 'icon' => $r['icon'], 'client' => $r['client'],
        'stat' => $r['stat'], 'statLabel' => $r['stat_label'], 'quote' => $r['quote'], 'body' => $r['body'],
    ], $rows);
}

/**
 * Live client projects an admin has ticked "show in the public portfolio"
 * on. Until now that checkbox wrote a column nothing ever read, so the
 * toggle did nothing at all — /work showed only the hand-typed case
 * studies. These are appended to those.
 */
function content_portfolio_rows(): array
{
    try {
        $rows = db()->query(
            "SELECT p.title, p.work_type, p.domain, b.name AS business_name, b.sector
             FROM projects p JOIN businesses b ON b.id = p.business_id
             WHERE p.portfolio_visible = 1 AND p.status = 'Live'
             ORDER BY p.created_at DESC LIMIT 12"
        )->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
    return array_map(fn($r) => [
        'client' => $r['business_name'],
        'sector' => $r['sector'],
        'title' => $r['title'],
        'workType' => $r['work_type'],
        'domain' => $r['domain'],
    ], $rows);
}

function content_pricing_faqs_rows(): array
{
    $rows = db()->query('SELECT question, answer FROM content_pricing_faqs ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => [$r['question'], $r['answer']], $rows);
}

function content_team_rows(): array
{
    $rows = db()->query('SELECT initials, name, role FROM content_team ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => ['i' => $r['initials'], 'n' => $r['name'], 'r' => $r['role']], $rows);
}

function content_values_rows(): array
{
    $rows = db()->query('SELECT icon, title, description FROM content_values ORDER BY sort_order ASC, id ASC')->fetchAll();
    return array_map(fn($r) => ['icon' => $r['icon'], 't' => $r['title'], 'd' => $r['description']], $rows);
}

function palette_attr(): string
{
    $p = get_setting('color_palette', '');
    return $p !== '' ? ' data-palette="' . e($p) . '"' : '';
}

function logo_motion_attr(): string
{
    return get_setting('logo_animation', 'on') === 'off' ? ' data-logo-motion="off"' : '';
}

/**
 * The site-wide "Site zoom" setting.
 *
 * This used to be emitted only as a viewport `initial-scale`, which
 * desktop browsers ignore entirely — so the slider did nothing on exactly
 * the screens where fitting more on the page matters most. It is now
 * applied on two axes that between them cover every visitor:
 *
 *  - ui_zoom_scale()  -> viewport initial-scale, the browser's own native
 *                        zoom on mobile (it can't disagree with itself the
 *                        way non-standard CSS `zoom` can).
 *  - ui_zoom_style()  -> a root font-size percentage, which desktop
 *                        browsers do honour. Every size in style.css that
 *                        matters is in rem/em, so this scales the layout
 *                        without breaking position:fixed the way
 *                        transform:scale would.
 */
function ui_zoom_percent(): int
{
    return max(50, min(150, (int)get_setting('ui_zoom', '100')));
}

function ui_zoom_scale(): string
{
    return number_format(ui_zoom_percent() / 100, 2, '.', '');
}

function ui_zoom_style(): string
{
    $zoom = ui_zoom_percent();
    return $zoom === 100 ? '' : '<style>:root{font-size:' . $zoom . '%;}</style>';
}

/**
 * The logo has four possible states, controlled by Admin > Settings >
 * Branding:
 *  - A custom uploaded image always wins: it's the whole logo, alone,
 *    no icon or text next to it.
 *  - Otherwise, "logo_style" picks between the built-in icon mark next
 *    to the site name text (default), the icon alone with no text, or
 *    the site name text alone with no icon.
 * logo_mark_html() returns the icon (image or built-in mark) or '' if
 * text-only mode applies; logo_wordmark_html() returns the site-name
 * text or '' once a custom image is set or icon-only mode applies.
 */
function logo_mark_html(bool $gradient = true, string $prefix = ''): string
{
    $path = get_setting('logo_path', '');
    if ($path !== '') {
        return '<span class="logo-mark"><img src="' . e($prefix . $path) . '" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;"></span>';
    }
    if (get_setting('logo_style', 'icon_text') === 'text_only') {
        return '';
    }
    $fill = $gradient ? 'url(#logoGrad)' : 'var(--accent-1)';
    return '<span class="logo-mark"><svg viewBox="0 0 24 24" fill="none"><g><rect x="3" y="3" width="18" height="18" rx="6" fill="' . $fill . '"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></g></svg></span>';
}

function logo_wordmark_html(): string
{
    if (get_setting('logo_path', '') !== '') {
        return '';
    }
    if (get_setting('logo_style', 'icon_text') === 'icon_only') {
        return '';
    }
    return '<b>' . e(get_setting('site_name', 'TECHBISS')) . '</b>';
}

/**
 * businesses.last_activity_at used to be written once, on insert, and never
 * again — while the admin list sorted by it under a "Last activity"
 * heading. Call this wherever something real happens for a business.
 */
function touch_business_activity(int $businessId): void
{
    if ($businessId <= 0) {
        return;
    }
    try {
        db()->prepare('UPDATE businesses SET last_activity_at = NOW() WHERE id = ?')->execute([$businessId]);
    } catch (Throwable $e) {
        // Never let a timestamp update break the action that triggered it.
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Every admin page answers a validation failure with flash() + redirect,
 * which re-renders the form empty — losing a long product description or
 * project note to a single mistyped email. Stash the submitted values so
 * the redirected page can put them back.
 */
function flash_input(array $input): void
{
    unset($input['csrf'], $input['action'], $input['password']);
    $_SESSION['flash_input'] = $input;
}

function old_input(string $key, $default = '')
{
    return $_SESSION['flash_input'][$key] ?? $default;
}

function take_old_input(): array
{
    $in = $_SESSION['flash_input'] ?? [];
    unset($_SESSION['flash_input']);
    return $in;
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 0) {
        $ahead = -$diff;
        if ($ahead < 3600) return 'in ' . max(1, (int)floor($ahead / 60)) . ' min';
        if ($ahead < 86400) return 'in ' . (int)floor($ahead / 3600) . ' hours';
        $days = (int)floor($ahead / 86400);
        return $days === 1 ? 'tomorrow' : 'in ' . $days . ' days';
    }
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    $days = floor($diff / 86400);
    return $days == 1 ? 'yesterday' : $days . ' days ago';
}
