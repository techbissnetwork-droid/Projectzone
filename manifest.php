<?php
/** The icons and name a phone uses if someone adds the site to their home screen. */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();

header('Content-Type: application/manifest+json; charset=UTF-8');

$name = setting('site.name', 'TECHBISS');
echo json_encode([
    'name'             => $name,
    'short_name'       => $name,
    'description'      => seo_description(''),
    'start_url'        => base_url() . '/',
    'display'          => 'standalone',
    'background_color' => '#0b0b0d',
    'theme_color'      => '#0b0b0d',
    'icons'            => [
        ['src' => url('assets/brand/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => url('assets/brand/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => url('assets/favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
