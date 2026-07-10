<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use RuntimeException;

class DatabaseConfigTest extends TestCase
{
    public function test_sqlite_does_not_require_mysql_fields(): void
    {
        putenv('DB_CONNECTION=sqlite');
        // Unset MySQL fields
        putenv('DB_HOST=');
        putenv('DB_DATABASE=');
        putenv('DB_USERNAME=');
        putenv('DB_PASSWORD=');
        
        $config = require __DIR__ . '/../../config/database.php';
        
        $this->assertSame('sqlite', $config['default']);
        $this->assertArrayHasKey('sqlite', $config['connections']);
        $this->assertSame('sqlite', $config['connections']['sqlite']['driver']);
        
        // Cleanup
        putenv('DB_CONNECTION=');
    }

    public function test_mysql_requires_explicit_credentials_and_fails_closed(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=');
        putenv('DB_DATABASE=');
        putenv('DB_USERNAME=');
        putenv('DB_PASSWORD=');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL configuration is incomplete');
        
        require __DIR__ . '/../../config/database.php';
        
        // Cleanup happens in tearDown typically or manual
    }

    public function test_mysql_succeeds_with_complete_config(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_DATABASE=test_db');
        putenv('DB_USERNAME=db_user');
        putenv('DB_PASSWORD=secret');
        
        $config = require __DIR__ . '/../../config/database.php';
        
        $this->assertSame('mysql', $config['default']);
        $this->assertSame('127.0.0.1', $config['connections']['mysql']['host']);
        $this->assertSame('test_db', $config['connections']['mysql']['database']);
        $this->assertSame('db_user', $config['connections']['mysql']['username']);
        $this->assertSame('secret', $config['connections']['mysql']['password']);
        
        // Cleanup
        putenv('DB_CONNECTION=');
        putenv('DB_HOST=');
        putenv('DB_DATABASE=');
        putenv('DB_USERNAME=');
        putenv('DB_PASSWORD=');
    }
}
