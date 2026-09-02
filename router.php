<?php
/**
 * Router for PHP's built-in server only, so that local testing behaves like
 * Apache: extensionless addresses resolve to .php, and a missing page shows
 * the site's own 404. Not used on real hosting — .htaccess does this there.
 *
 *   php -S localhost:8000 router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the real file
}
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}
if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/404.php';
return true;
