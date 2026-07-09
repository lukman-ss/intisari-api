<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use PDO;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Repositories\UserRepository;

class UserRepositoryTest extends TestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

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
        
        $this->repo = new UserRepository($this->pdo);
    }

    public function test_can_create_and_find_user(): void
    {
        $user = $this->repo->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password_hash' => 'hashed_pwd'
        ]);
        
        $this->assertNotEmpty($user);
        $this->assertArrayHasKey('id', $user);
        $this->assertSame('John Doe', $user['name']);
        
        $foundById = $this->repo->findById((int)$user['id']);
        $this->assertSame($user['email'], $foundById['email']);
        
        $foundByEmail = $this->repo->findByEmail('john@example.com');
        $this->assertSame($user['id'], $foundByEmail['id']);
    }

    public function test_can_update_user(): void
    {
        $user = $this->repo->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password_hash' => 'secret'
        ]);
        
        $updated = $this->repo->update((int)$user['id'], [
            'name' => 'Alice Updated',
            'is_active' => 0
        ]);
        
        $this->assertSame('Alice Updated', $updated['name']);
        $this->assertSame(0, (int)$updated['is_active']);
    }

    public function test_can_delete_user(): void
    {
        $user = $this->repo->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password_hash' => 'secret'
        ]);
        
        $this->assertTrue($this->repo->delete((int)$user['id']));
        
        $found = $this->repo->findById((int)$user['id']);
        $this->assertNull($found);
    }

    public function test_can_paginate_users(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->repo->create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password_hash' => 'secret'
            ]);
        }
        
        $result = $this->repo->paginate(1, 10);
        
        $this->assertCount(10, $result['items']);
        $this->assertSame(1, $result['meta']['current_page']);
        $this->assertSame(10, $result['meta']['per_page']);
        $this->assertSame(25, $result['meta']['total']);
        $this->assertSame(3, $result['meta']['last_page']);
        
        $resultPage3 = $this->repo->paginate(3, 10);
        $this->assertCount(5, $resultPage3['items']);
    }
}
