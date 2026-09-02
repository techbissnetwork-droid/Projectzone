<?php
/**
 * Copy this file to config.php and fill in your details.
 * config.php is git-ignored so your credentials never reach the repository.
 */
return [
    'db' => [
        // 'mysql' on cPanel/shared hosting. 'sqlite' needs no database server,
        // which is handy for testing the site on your own machine first.
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'name'        => 'yourcpaneluser_techbiss',
        'user'        => 'yourcpaneluser_techbiss',
        'pass'        => '',
        'charset'     => 'utf8mb4',
        'sqlite_path' => __DIR__ . '/../storage/techbiss.sqlite',
    ],

    'mail' => [
        // Where enquiries are delivered.
        'to'   => 'hello@techbiss.com',
        // MUST be an address on your own domain or the host will refuse to send,
        // and receiving servers will treat the mail as spoofed.
        'from' => 'website@techbiss.com',
        'from_name' => 'TECHBISS Website',
    ],

    // Leave empty when the site is at the domain root. Set to '/subfolder'
    // if you upload into a subdirectory.
    'base_url' => '',

    // Turn on only while debugging. Never leave true on a live site.
    'debug' => false,
];
