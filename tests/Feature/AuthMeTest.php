<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Intisari\Application;
use Lukman\Http\Request;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Support\PasswordHasher;
use App\Support\TokenService;
use PDO;

class AuthMeTest extends TestCase
{
    private TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed user
        $hasher = new PasswordHasher();
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Demo User', 'demo@example.com', $hasher->hash('secret123'), 1]);
        
        $this->tokenService = $this->app->make(TokenService::class);
    }

    public function test_it_returns_current_user_when_authenticated(): void
    {
        $tokenData = $this->tokenService->createToken(1, 'test');
        $token = $tokenData['plain_token'];

        $headers = [
            'Authorization' => "Bearer {$token}",
        ];

        $request = new Request('GET', '/api/auth/me', [], [], $headers, '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('OK', $body['message']);
        $this->assertSame('demo@example.com', $body['data']['user']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']['user']);
    }

    public function test_it_rejects_unauthenticated_request(): void
    {
        $request = new Request('GET', '/api/auth/me', [], [], [], '');
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Unauthenticated', $body['message']);
    }
}
