<?php
/**
 * iamtomley — application configuration
 * ------------------------------------------------------------------
 * Storage defaults to a zero-config SQLite database (data/app.db).
 * To use MySQL instead, set DB_DRIVER to 'mysql' and fill in the
 * credentials below — the rest of the app is driver-agnostic (PDO).
 */

declare(strict_types=1);

// ── Paths ─────────────────────────────────────────────────────────
define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');
define('UPLOAD_DIR', APP_ROOT . '/uploads');

// ── Local overrides (written by the installer) ────────────────────
// A protected file in data/ may predefine any of the constants below
// (DB_DRIVER, DB_HOST, …, APP_BASE_URL). It's optional: without it the
// app runs on zero-config SQLite.
if (is_file(DATA_DIR . '/config.local.php')) {
    require DATA_DIR . '/config.local.php';
}

// ── Database ──────────────────────────────────────────────────────
// 'sqlite' (default, no setup) or 'mysql'
defined('DB_DRIVER') || define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');

// SQLite
defined('DB_SQLITE_PATH') || define('DB_SQLITE_PATH', DATA_DIR . '/app.db');

// MySQL (only used when DB_DRIVER === 'mysql')
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'iamtomley');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ── Site ──────────────────────────────────────────────────────────
// Base URL path (auto-detected so the app works at the web root OR in a
// subdirectory, on any host). You can override it explicitly by setting the
// APP_BASE_URL environment variable (e.g. "" for root, "/portfolio" for a
// subfolder) if your server's paths are unusual.
$__base = defined('APP_BASE_URL') ? APP_BASE_URL : getenv('APP_BASE_URL');
if ($__base === false || $__base === null) {
    $__base = '';
    // Derive the base from the running script's URL vs. filesystem path — this
    // does not depend on DOCUMENT_ROOT, which is unreliable across hosts.
    $__sf = isset($_SERVER['SCRIPT_FILENAME'])
        ? str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME']))
        : '';
    $__sn = isset($_SERVER['SCRIPT_NAME'])
        ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
        : '';
    $__app = str_replace('\\', '/', (string) realpath(APP_ROOT));
    if ($__sf !== '' && $__sn !== '' && $__app !== '' && strpos($__sf, $__app) === 0) {
        // Path of the running script relative to the app root, e.g. /admin/x.php
        $__rel = substr($__sf, strlen($__app));           // "/admin/settings.php"
        if ($__rel !== '' && str_ends_with($__sn, $__rel)) {
            $__base = substr($__sn, 0, strlen($__sn) - strlen($__rel));
        }
    }
    // Fallback: DOCUMENT_ROOT comparison (older behaviour).
    if ($__base === '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $__dr = str_replace('\\', '/', rtrim((string) realpath($_SERVER['DOCUMENT_ROOT']), '/'));
        if ($__dr !== '' && $__app !== '' && $__app !== $__dr && strpos($__app, $__dr) === 0) {
            $__base = substr($__app, strlen($__dr));
        }
    }
}
define('BASE_URL', rtrim((string) $__base, '/'));

// Session hardening
defined('SESSION_NAME') || define('SESSION_NAME', 'iamtomley_sess');

// Installation lock (written by install/ wizard)
define('INSTALL_LOCK', DATA_DIR . '/installed.lock');

// Brute-force login lockout
defined('LOGIN_MAX_FAILS') || define('LOGIN_MAX_FAILS', 6);      // per window, per IP
defined('LOGIN_WINDOW')    || define('LOGIN_WINDOW', 900);       // 15 minutes

// ── Production hardening ──────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    @ini_set('display_errors', '0');        // never leak errors/paths to visitors
    @ini_set('log_errors', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS'])) { @ini_set('session.cookie_secure', '1'); }
}
error_reporting(E_ALL);
