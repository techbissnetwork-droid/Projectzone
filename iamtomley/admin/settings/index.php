<?php
/**
 * settings.php served without .php in the address.
 *
 * A folder with an index.php is served by every web server without a single
 * rewrite rule, so this works on hosts where mod_rewrite is off or .htaccess
 * is ignored. The real page is one directory up; __DIR__ inside it still
 * points at its own folder, so its own includes resolve exactly as before.
 */
declare(strict_types=1);
require __DIR__ . '/../settings.php';
