<?php
/**
 * Local-only helper for PHP's built-in dev server (`php -S 127.0.0.1:8000
 * router.php`), which — unlike Apache — doesn't read .htaccess. Mirrors
 * the same "serve real files as-is, everything else goes to index.php"
 * rule that .htaccess applies on real hosting. Not used in production.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}
require __DIR__ . '/index.php';
