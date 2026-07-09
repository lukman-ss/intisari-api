<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use PDO;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Support\TokenService;

class TokenServiceTest extends TestCase
{
    private TokenService $service;

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
        
        $this->service = new TokenService($this->pdo);

        // create dummy user
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Test', 'test@example.com', 'pwd')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (2, 'Test 2', 'test2@example.com', 'pwd')");
    }

    public function test_can_create_token_and_returns_plain_text(): void
    {
        putenv('AUTH_TOKEN_TTL_MINUTES=1440');
        putenv('AUTH_TOKEN_HASH_ALGO=sha256');
        
        $result = $this->service->createToken(1, 'mobile', ['read', 'write']);
        
        $this->assertArrayHasKey('plain_token', $result);
        $this->assertArrayHasKey('token', $result);
        
        $plain = $result['plain_token'];
        $token = $result['token'];
        
        $this->assertGreaterThanOrEqual(40, strlen($plain));
        $this->assertSame(1, (int) $token['user_id']);
        $this->assertSame('mobile', $token['name']);
        
        // Ensure abilities are array
        $this->assertIsArray($token['abilities']);
        $this->assertContains('read', $token['abilities']);
    }

    public function test_can_find_valid_token(): void
    {
        $result = $this->service->createToken(1);
        $plain = $result['plain_token'];
        
        $token = $this->service->findValidToken($plain);
        
        $this->assertNotNull($token);
        $this->assertSame($result['token']['id'], $token['id']);
        $this->assertNotNull($token['last_used_at']);
        
        // Invalid token
        $this->assertNull($this->service->findValidToken('invalid_token_string_here'));
    }

    public function test_find_valid_token_rejects_expired_tokens(): void
    {
        $result = $this->service->createToken(1);
        $plain = $result['plain_token'];
        
        // Expire token manually in DB
        $this->pdo->exec("UPDATE api_tokens SET expires_at = '2000-01-01 00:00:00'");
        
        $token = $this->service->findValidToken($plain);
        
        $this->assertNull($token, 'Expired token should return null');
    }

    public function test_can_revoke_token(): void
    {
        $result = $this->service->createToken(1);
        $plain = $result['plain_token'];
        
        $this->assertTrue($this->service->revokeToken($plain));
        
        // Verify it is gone
        $this->assertNull($this->service->findValidToken($plain));
        $this->assertFalse($this->service->revokeToken($plain));
    }

    public function test_can_revoke_all_tokens_for_user(): void
    {
        $this->service->createToken(1);
        $this->service->createToken(1);
        $this->service->createToken(1);
        
        // Another user
        $this->service->createToken(2);
        
        $revokedCount = $this->service->revokeAllForUser(1);
        
        $this->assertSame(3, $revokedCount);
        
        // Check database
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM api_tokens");
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Should leave user 2 token intact');
    }
}
