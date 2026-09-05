<?php
require_once __DIR__ . '/mailer.php';

define('CONFIG_PATH', __DIR__ . '/../config.php');
define('INSTALL_LOCK_PATH', __DIR__ . '/../install.lock');

if (file_exists(CONFIG_PATH)) {
    require_once CONFIG_PATH;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_installed(): bool
{
    return file_exists(CONFIG_PATH) && file_exists(INSTALL_LOCK_PATH) && defined('DB_HOST');
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
        echo json_encode(['error' => APP_DEBUG
            ? ('Database connection failed: ' . $e->getMessage())
            : 'Database connection failed. Check config.php and that schema.sql has been imported.']);
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
    $recent = $pdo->prepare(
        'SELECT id FROM otp_codes WHERE customer_id = ? AND purpose = ? AND used_at IS NULL AND created_at > (NOW() - INTERVAL 60 SECOND)'
    );
    $recent->execute([$customerId, $purpose]);
    if ($recent->fetch()) {
        throw new RuntimeException('Please wait a moment before requesting another code.');
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

function require_staff_access(array $staff, string $page): void
{
    if (!staff_can($staff, $page)) {
        flash("You don't have access to that section.", 'error');
        header('Location: index.php');
        exit;
    }
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
 * A single admin-set zoom level applied to every visitor, on both the
 * public site and the admin panel — lets an admin shrink the whole UI
 * so more fits on small screens without touching individual pages.
 * zoom (not transform:scale) is used specifically because it doesn't
 * break position:fixed headers/docks/modals the way scale would.
 */
function ui_zoom_attr(): string
{
    $zoom = max(50, min(150, (int)get_setting('ui_zoom', '100')));
    return $zoom !== 100 ? ' style="zoom:' . $zoom . '%"' : '';
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

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
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
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    $days = floor($diff / 86400);
    return $days == 1 ? 'yesterday' : $days . ' days ago';
}
