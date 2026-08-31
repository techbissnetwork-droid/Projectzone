<?php
/**
 * TECHBISS — application configuration.
 *
 * Copy this file to config/config.php and adjust the values for your server.
 * config/config.php is gitignored so credentials never reach the repository.
 */

return [
    // ---------------------------------------------------------------------
    // Database (MySQL 5.7+ / MariaDB 10.4+)
    // ---------------------------------------------------------------------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'techbiss',
        'user'     => 'techbiss',
        'pass'     => '',
        'charset'  => 'utf8mb4',
        'socket'   => '', // optional unix socket path, overrides host/port when set
    ],

    // ---------------------------------------------------------------------
    // Site
    // ---------------------------------------------------------------------
    'site' => [
        // Absolute base URL without trailing slash, e.g. https://techbiss.com
        // Leave empty to auto-detect from the request.
        'url'      => '',
        // Sub-directory the app is served from, e.g. '/techbiss'. Empty for domain root.
        'base_path' => '',
        'timezone' => 'UTC',
        'locale'   => 'en',
        'debug'    => false,
    ],

    // ---------------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------------
    'security' => [
        // Change this to a long random string. Used for CSRF + cookie signing.
        'app_key'            => 'change-me-to-a-long-random-string',
        'session_name'       => 'techbiss_session',
        'session_lifetime'   => 7200,     // seconds
        'cookie_secure'      => false,    // set true when serving over HTTPS
        'login_max_attempts' => 6,
        'login_lockout'      => 900,      // seconds
    ],

    // ---------------------------------------------------------------------
    // Uploads
    // ---------------------------------------------------------------------
    'uploads' => [
        'dir'            => __DIR__ . '/../uploads',
        'max_bytes'      => 6 * 1024 * 1024,
        'max_width'      => 6000,
        'max_height'     => 6000,
        'allowed_mime'   => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
        ],
        'thumb_width'    => 480,
    ],

    // ---------------------------------------------------------------------
    // Mail — used for lead notifications. 'mail' uses PHP mail(), 'log'
    // writes to storage/logs/mail.log, 'none' disables delivery entirely.
    // ---------------------------------------------------------------------
    'mail' => [
        'driver' => 'log',
        'from'   => 'no-reply@techbiss.com',
        'name'   => 'TECHBISS',
    ],

    // ---------------------------------------------------------------------
    // Cache — file cache for settings/navigation lookups.
    // ---------------------------------------------------------------------
    'cache' => [
        'enabled' => true,
        'dir'     => __DIR__ . '/../storage/cache',
        'ttl'     => 300,
    ],
];
