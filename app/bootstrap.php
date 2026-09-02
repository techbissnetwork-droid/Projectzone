<?php
declare(strict_types=1);

/**
 * Never the entry point. The web server should not serve this folder at all,
 * but a server without .htaccess support (nginx) would otherwise run this file
 * directly if someone asked for it by name.
 */
if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}

/**
 * Boots the application: config, database, session, settings.
 * Every entry point includes this file first.
 */

if (PHP_VERSION_ID < 80100) {
    exit('TECHBISS requires PHP 8.1 or newer. This server runs ' . PHP_VERSION . '.');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Flash.php';
require_once __DIR__ . '/Upload.php';
require_once __DIR__ . '/Content.php';
require_once __DIR__ . '/Payments.php';
require_once __DIR__ . '/Mail.php';
require_once __DIR__ . '/LoginCode.php';

$configFile = base_path('config/config.php');
if (!is_file($configFile)) {
    if (!defined('TB_INSTALLING')) {
        header('Location: ' . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/') . '/install/');
        exit;
    }
    return;
}

/** @var array $TB_CONFIG */
$TB_CONFIG = require $configFile;
$GLOBALS['TB_CONFIG'] = $TB_CONFIG;

date_default_timezone_set($TB_CONFIG['app']['timezone'] ?? 'UTC');

$debug = (bool)($TB_CONFIG['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (!$debug) {
    set_exception_handler(static function (Throwable $ex): void {
        error_log('[TECHBISS] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
           . '<style>body{background:#06070A;color:#EEF1F6;font:16px/1.6 system-ui,sans-serif;'
           . 'display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px}'
           . 'a{color:#8FB0FF}</style>'
           . '<div><h1 style="font-size:1.4rem;margin:0 0 8px">Something went wrong</h1>'
           . '<p style="color:#9AA3B2;margin:0 0 16px">The error has been logged. Please try again.</p>'
           . '<a href="' . e(url()) . '">Back to the site</a></div>';
        exit;
    });
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('techbiss_session');
    session_start();
}

try {
    Database::connect($TB_CONFIG['db']);
} catch (PDOException $ex) {
    error_log('[TECHBISS] DB: ' . $ex->getMessage());
    http_response_code(503);
    exit('The database is unavailable. Check config/config.php.');
}

Settings::load();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
