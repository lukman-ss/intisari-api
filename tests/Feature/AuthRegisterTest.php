<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Intisari\Application;
use Lukman\Http\Request;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use PDO;

class AuthRegisterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        }

    public function test_it_registers_user_successfully(): void
    {
        $payload = [
            'name' => 'Demo User',
            'email' => 'demo@EXAMPLE.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $request = new Request('POST', '/api/auth/register', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(201, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('Registered', $body['message']);
        $this->assertArrayHasKey('token', $body['data']);
        $this->assertSame('demo@example.com', $body['data']['user']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']['user']);

        // Check database
        $stmt = $this->pdo->query("SELECT * FROM users WHERE email = 'demo@example.com'");
        $userInDb = $stmt->fetch();
        $this->assertIsArray($userInDb);
        $this->assertSame('Demo User', $userInDb['name']);
        
        // Token database
        $stmt = $this->pdo->query("SELECT * FROM api_tokens WHERE user_id = " . $userInDb['id']);
        $tokenInDb = $stmt->fetch();
        $this->assertIsArray($tokenInDb);
    }

    public function test_it_rejects_missing_fields(): void
    {
        $request = new Request('POST', '/api/auth/register', [], [], [], json_encode([]));
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('name', $body['errors']);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertArrayHasKey('password', $body['errors']);
    }

    public function test_it_rejects_unconfirmed_password(): void
    {
        $payload = [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret124' // different
        ];

        $request = new Request('POST', '/api/auth/register', [], [], [], json_encode($payload));
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertArrayHasKey('password', $body['errors']);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        // First register
        $payload = [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];
        $this->app->handle(new Request('POST', '/api/auth/register', [], [], [], json_encode($payload)));
        
        // Second register
        $response = $this->app->handle(new Request('POST', '/api/auth/register', [], [], [], json_encode($payload)));
        
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertStringContainsString('already been taken', $body['errors']['email'][0]);
    }
}
