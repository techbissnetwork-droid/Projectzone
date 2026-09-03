<?php
declare(strict_types=1);

/**
 * PSR-4 style autoloader for the App\ namespace. Avoids a Composer install so
 * the platform deploys by copying files to any PHP 8.1+ host.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';
