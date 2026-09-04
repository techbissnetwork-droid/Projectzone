<?php
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

function current_staff(): ?array
{
    if (empty($_SESSION['staff_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, role FROM staff WHERE id = ?');
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
