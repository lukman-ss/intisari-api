<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use PDO;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

class UsersMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan sqlite in-memory
        $this->pdo = ConnectionFactory::make([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]
        ]);
        
        $runner = new MigrationRunner($this->pdo);
        $runner->run(dirname(__DIR__, 2) . '/database/migrations');
    }

    public function test_users_table_has_expected_columns(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(users)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertNotEmpty($columns, 'Table users should exist');
        
        $columnNames = array_column($columns, 'name');
        
        $expectedColumns = [
            'id',
            'name',
            'email',
            'password_hash',
            'is_active',
            'created_at',
            'updated_at'
        ];
        
        foreach ($expectedColumns as $expected) {
            $this->assertContains($expected, $columnNames);
        }
    }
    
    public function test_can_insert_user_and_defaults_are_respected(): void
    {
        $passwordHash = password_hash('secret', PASSWORD_BCRYPT);
        
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute(['Budi', 'budi@example.com', $passwordHash]);
        
        $userId = $this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $userId);
        
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame('Budi', $user['name']);
        $this->assertSame('budi@example.com', $user['email']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertNotEmpty($user['created_at']);
        $this->assertNotEmpty($user['updated_at']);
    }
    
    public function test_email_must_be_unique(): void
    {
        $passwordHash = password_hash('secret', PASSWORD_BCRYPT);
        
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute(['Ahmad', 'ahmad@example.com', $passwordHash]);
        
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed: users.email/');
        
        $stmt->execute(['Ahmad Clone', 'ahmad@example.com', $passwordHash]);
    }
}
