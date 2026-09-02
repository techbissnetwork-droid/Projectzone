<?php
/**
 * Every page starts here. Loads config, opens the session, wires the helpers.
 */

declare(strict_types=1);

/* Always include this file with require_once — its functions are bound when
   the file is compiled, so a runtime guard could not prevent a redeclare. */

if (PHP_VERSION_ID < 80100) {
    exit('This application needs PHP 8.1 or newer. Your host is running ' . PHP_VERSION . '.');
}

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

require_once APP_DIR . '/helpers.php';
require_once APP_DIR . '/db.php';
require_once APP_DIR . '/schema.php';
require_once APP_DIR . '/auth.php';
require_once APP_DIR . '/content.php';
require_once APP_DIR . '/mailer.php';
require_once APP_DIR . '/reminders.php';

/**
 * Config, loaded once.
 *
 * The installer has no config.php yet, so it puts the details it collected in
 * $GLOBALS['__install_config'] and this returns those instead — that is the
 * only time the override is set.
 */
function config(): array
{
    if (!empty($GLOBALS['__install_config'])) {
        return $GLOBALS['__install_config'];
    }
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_DIR . '/config.php';
        $cfg  = is_file($file) ? (require $file) : [];
    }
    return is_array($cfg) ? $cfg : [];
}

function is_installed(): bool
{
    return is_file(APP_DIR . '/config.php') && !empty(config());
}

/** Session, with sensible cookie flags. */
function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
        'path'     => '/',
    ]);
    session_name('techbiss_session');
    session_start();
}

session_start_safe();

/**
 * Send anyone who has not installed yet to the installer — except the
 * installer itself, which handles that case on its own.
 */
function require_installed(): void
{
    if (is_installed()) {
        return;
    }
    $depth = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/')
          || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/client/') ? '../' : '';
    header('Location: ' . $depth . 'install.php');
    exit;
}

/** Errors: shown while developing, logged on real hosting. */
$displayErrors = getenv('TECHBISS_DEBUG') === '1';
ini_set('display_errors', $displayErrors ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($displayErrors) {
    error_log('[techbiss] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($displayErrors) {
        echo '<pre style="padding:20px;font:13px monospace;background:#111;color:#f4f4f6">';
        echo htmlspecialchars((string) $e);
        echo '</pre>';
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>';
    echo '<div style="font:16px/1.6 system-ui;max-width:38rem;margin:18vh auto;padding:0 6vw;'
       . 'background:#0b0b0d;color:#f4f4f6">';
    echo '<h1 style="font-size:1.6rem">Something went wrong on our side.</h1>';
    echo '<p style="color:#8b8b93">The error has been logged. Please try again, and if it keeps '
       . 'happening email <a style="color:#c9ff3d" href="mailto:support@techbiss.com">'
       . 'support@techbiss.com</a>.</p></div>';
});
