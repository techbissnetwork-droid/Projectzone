<?php
declare(strict_types=1);

/**
 * TECHBISS platform front controller.
 *
 * Everything a request needs is resolved in a single pass: no framework boot,
 * no dependency graph, no autoload map to warm. Static files are handed back
 * to the PHP dev server directly so `php -S` works without a rewrite layer.
 */

use App\Core\Application;
use App\Core\Request;

$root = dirname(__DIR__);

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if (is_file($file)) {
        return false;
    }
}

require $root . '/app/Support/autoload.php';

$app = new Application($root);

date_default_timezone_set((string) $app->config()->get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

if ($app->isDebug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

// Transparent output compression when no upstream layer already handles it.
if (!ini_get('zlib.output_compression') && extension_loaded('zlib')) {
    ini_set('zlib.output_compression', '1');
    ini_set('zlib.output_compression_level', '6');
}

require $root . '/app/routes.php';

$request = Request::capture();
$response = $app->handle($request);

$response
    ->withHeader('X-Content-Type-Options', 'nosniff')
    ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
    ->withHeader('X-Frame-Options', 'SAMEORIGIN')
    ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()')
    ->withHeader('Vary', 'Accept-Encoding')
    ->send($request->method !== 'HEAD');
