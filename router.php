<?php
/**
 * Only for running the site locally with PHP's built-in server:
 *
 *     php -S localhost:8000 router.php
 *
 * On real hosting .htaccess does the same job and this file is ignored.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;   // let the built-in server serve the static file
}
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}
if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}
if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/404.php';
return true;
