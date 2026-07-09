<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class ConnectionFactory
{
    public static function make(array $config = null): PDO
    {
        if ($config === null) {
            $config = config('database', []);
        }

        $default = $config['default'] ?? 'sqlite';
        $dbConfig = $config['connections'][$default] ?? [];

        if ($default === 'sqlite') {
            $database = $dbConfig['database'] ?? ':memory:';
            $pdo = new PDO("sqlite:{$database}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            if ($dbConfig['foreign_key_constraints'] ?? true) {
                $pdo->exec('PRAGMA foreign_keys = ON;');
            }
            
            return $pdo;
        }

        throw new \RuntimeException("Unsupported database driver: {$default}");
    }
}
