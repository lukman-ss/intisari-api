<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use PDO;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

class ApiTokensMigrationTest extends TestCase
{
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
    }

    public function test_api_tokens_table_has_expected_columns(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(api_tokens)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertNotEmpty($columns, 'Table api_tokens should exist');
        
        $columnNames = array_column($columns, 'name');
        
        $expectedColumns = [
            'id',
            'user_id',
            'name',
            'token_hash',
            'abilities',
            'last_used_at',
            'expires_at',
            'created_at'
        ];
        
        foreach ($expectedColumns as $expected) {
            $this->assertContains($expected, $columnNames);
        }
    }
    
    public function test_can_insert_api_token_with_foreign_key(): void
    {
        // First insert a user to satisfy foreign key
        $this->pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Test', 'test@example.com', 'pwd')");
        $userId = (int) $this->pdo->lastInsertId();
        
        $tokenRaw = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenRaw);
        
        $stmt = $this->pdo->prepare("INSERT INTO api_tokens (user_id, name, token_hash) VALUES (?, ?, ?)");
        $stmt->execute([$userId, 'Personal Access Token', $tokenHash]);
        
        $tokenId = $this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $tokenId);
        
        $stmt = $this->pdo->prepare("SELECT * FROM api_tokens WHERE id = ?");
        $stmt->execute([$tokenId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame($userId, (int) $token['user_id']);
        $this->assertSame('Personal Access Token', $token['name']);
        $this->assertSame($tokenHash, $token['token_hash']);
        $this->assertNull($token['abilities']);
        $this->assertNull($token['last_used_at']);
        $this->assertNull($token['expires_at']);
        $this->assertNotEmpty($token['created_at']);
    }
    
    public function test_token_hash_must_be_unique(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Test2', 'test2@example.com', 'pwd')");
        $userId = (int) $this->pdo->lastInsertId();
        
        $tokenHash = hash('sha256', 'same_token');
        
        $stmt = $this->pdo->prepare("INSERT INTO api_tokens (user_id, token_hash) VALUES (?, ?)");
        $stmt->execute([$userId, $tokenHash]);
        
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed: api_tokens.token_hash/');
        
        $stmt->execute([$userId, $tokenHash]);
    }
}
