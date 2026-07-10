<?php

declare(strict_types=1);

$connection = getenv('DB_CONNECTION') ?: 'sqlite';

$mysqlConfig = [];
if ($connection === 'mysql') {
    $host = getenv('DB_HOST');
    $database = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');

    // Strict validation for production safety
    if (!$host || !$database || !$username || $password === false) {
        throw new \RuntimeException('MySQL configuration is incomplete. DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD are required.');
    }

    $mysqlConfig = [
        'driver' => 'mysql',
        'host' => $host,
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ];
}

return [
    'default' => $connection,

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => (function () {
                $dbPath = getenv('DB_DATABASE') ?: dirname(__DIR__) . '/database/api.sqlite';
                if ($dbPath !== ':memory:') {
                    $dir = dirname($dbPath);
                    if (!is_dir($dir) || !is_writable($dir)) {
                        throw new \RuntimeException("SQLite database directory is not writable or does not exist: {$dir}");
                    }
                    $publicDir = dirname(__DIR__) . '/public';
                    if (str_starts_with(realpath($dir) ?: $dir, realpath($publicDir) ?: $publicDir)) {
                        throw new \RuntimeException("SQLite database must not be placed in the public web root.");
                    }
                }
                return $dbPath;
            })(),
            'prefix' => '',
            'foreign_key_constraints' => getenv('DB_FOREIGN_KEYS') ?: true,
        ],

        'mysql' => $mysqlConfig,
    ],
];
