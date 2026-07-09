<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Intisari\Application;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        
        $this->app = require dirname(__DIR__) . '/bootstrap/app.php';
        
        $this->pdo = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ]);
        
        // Ensure foreign keys are enabled in SQLite
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        
        $this->app->singleton(PDO::class, function() {
            return $this->pdo;
        });
        
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $this->migrateFresh();
    }
    
    protected function migrateFresh(): void
    {
        $runner = new MigrationRunner($this->pdo);
        $runner->run(dirname(__DIR__) . '/database/migrations');
    }
}
