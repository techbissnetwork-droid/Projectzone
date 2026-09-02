<?php
/** Loaded first by every page, public and admin. */

require __DIR__ . '/helpers.php';

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require __DIR__ . '/db.php';
require __DIR__ . '/schema.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mailer.php';

/**
 * If the database is not set up yet, send visitors to the installer rather
 * than showing a stack trace.
 */
function require_installed(): void
{
    if (!is_file(__DIR__ . '/config.php') || !schema_installed()) {
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
            redirect('install.php');
        }
    }
}
