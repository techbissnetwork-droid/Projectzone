<?php
/**
 * TECHBISS — application configuration.
 *
 * You do not normally need this file: open the site in a browser and the setup
 * wizard writes config/config.php for you, with the site URL detected and an
 * application key generated.
 *
 * Copy it manually only if you would rather configure by hand. Either way
 * config/config.php is gitignored, so credentials never reach the repository.
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
        // Absolute base URL without a trailing slash, e.g. https://techbiss.com
        // or https://example.com/clients/acme for a sub-directory install.
        // Leave empty to detect scheme, host and sub-directory per request —
        // useful while a site is moving between domains or environments.
        'url'      => '',
        // Normally left empty: taken from 'url' above, or detected. Set it only
        // to override both, e.g. behind a proxy that rewrites the path.
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
