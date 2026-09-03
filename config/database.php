<?php
declare(strict_types=1);

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => dirname(__DIR__) . '/storage/db/techbiss.sqlite',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'techbiss',
            'username' => 'techbiss',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
