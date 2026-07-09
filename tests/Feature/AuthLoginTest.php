<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Intisari\Application;
use Lukman\Http\Request;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Support\PasswordHasher;
use PDO;

class AuthLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed user
        $hasher = new PasswordHasher();
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Demo User', 'demo@example.com', $hasher->hash('secret123'), 1]);
        $stmt->execute(['Inactive User', 'inactive@example.com', $hasher->hash('secret123'), 0]);
    }

    public function test_it_logs_in_successfully(): void
    {
        $payload = [
            'email' => 'demo@example.com',
            'password' => 'secret123'
        ];

        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('Logged in', $body['message']);
        $this->assertArrayHasKey('token', $body['data']);
        $this->assertSame('demo@example.com', $body['data']['user']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']['user']);
    }

    public function test_it_rejects_invalid_password(): void
    {
        $payload = [
            'email' => 'demo@example.com',
            'password' => 'wrongpassword'
        ];

        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid credentials', $body['message']);
    }

    public function test_it_rejects_nonexistent_email(): void
    {
        $payload = [
            'email' => 'notfound@example.com',
            'password' => 'secret123'
        ];

        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
    }

    public function test_it_rejects_inactive_user(): void
    {
        $payload = [
            'email' => 'inactive@example.com',
            'password' => 'secret123'
        ];

        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
    }

    public function test_it_rejects_missing_fields(): void
    {
        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode([]));
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertArrayHasKey('password', $body['errors']);
    }
}
