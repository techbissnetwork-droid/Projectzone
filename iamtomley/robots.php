<?php
/**
 * robots.txt — served from PHP so it can react to the site's own state.
 *
 * Before setup, during maintenance, or while "discourage search engines" is on,
 * it closes the whole site off. Otherwise it opens the public page, keeps the
 * admin and internals out of the index, and points at the sitemap.
 *
 * Apache maps /robots.txt here (see .htaccess). Without mod_rewrite the file is
 * still reachable at /robots.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex');

$closed = true;
if (is_installed()) {
    $closed = setting('maintenance_mode', '0') === '1' || setting('seo_noindex', '0') === '1';
}

if ($closed) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

$base = BASE_URL;

echo "User-agent: *\n";
echo "Allow: {$base}/\n";
// The admin panel, the installer and the app's internals are never content.
foreach (['/admin/', '/install/', '/includes/', '/data/', '/config.php', '/game.php'] as $path) {
    echo "Disallow: {$base}{$path}\n";
}
echo "\n";
echo "Sitemap: " . site_url('/sitemap.xml') . "\n";
