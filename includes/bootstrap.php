<?php
declare(strict_types=1);

/**
 * TECHBISS bootstrap — autoloading, configuration, database, session.
 * Included by the public front controller, the admin panel and the API.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('TECHBISS requires PHP 8.1 or newer. This server is running ' . PHP_VERSION . '.');
}

foreach (['pdo_mysql', 'mbstring', 'json'] as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        exit('The PHP extension "' . $ext . '" is required but not enabled.');
    }
}

define('TECHBISS_ROOT', dirname(__DIR__));
define('TECHBISS_START', microtime(true));

// PSR-4 style autoloader for the Techbiss\ namespace → includes/
spl_autoload_register(static function (string $class): void {
    $prefix = 'Techbiss\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = TECHBISS_ROOT . '/includes/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require TECHBISS_ROOT . '/includes/helpers.php';

Techbiss\Core\App::boot(TECHBISS_ROOT);
