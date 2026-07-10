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
        
        $this->assertSame(400, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        // Ensure generic message, no leaking of taken, exists, user ID, SQL, or table name
        $jsonBody = json_encode($body);
        $this->assertStringNotContainsStringIgnoringCase('taken', $jsonBody);
        $this->assertStringNotContainsStringIgnoringCase('exists', $jsonBody);
        $this->assertStringNotContainsStringIgnoringCase('SQL', $jsonBody);
        $this->assertStringNotContainsStringIgnoringCase('users', $jsonBody);
        $this->assertFalse($body['success']);
        $this->assertSame('Registration could not be completed.', $body['message']);
        
        // Note: Full elimination of enumeration requires email verification flow.
    }

    public function test_it_rejects_duplicate_email_different_case(): void
    {
        $payload1 = [
            'name' => 'Demo User',
            'email' => 'Case@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $payload2 = [
            'name' => 'Demo User',
            'email' => 'case@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $this->app->handle(new Request('POST', '/api/auth/register', [], [], [], json_encode($payload1)));
        $response = $this->app->handle(new Request('POST', '/api/auth/register', [], [], [], json_encode($payload2)));

        $this->assertSame(400, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertSame('Registration could not be completed.', $body['message']);
    }

    public function test_race_condition_unique_constraint_violation(): void
    {
        $payload = [
            'name' => 'Race Condition',
            'email' => 'race@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        // Insert directly to bypass findByEmail check inside register logic
        $userRepo = app(\App\Repositories\UserRepository::class);
        $userRepo->create([
            'name' => 'Race Condition',
            'email' => 'race@example.com',
            'password_hash' => 'hash'
        ]);

        // Attempting to register via API should fail at the PDO level during insert
        // To really test PDO exception, we would mock UserRepository
        $mockRepo = $this->createMock(\App\Repositories\UserRepository::class);
        $mockRepo->method('findByEmail')->willReturn(null);
        $mockRepo->method('create')->willThrowException(new \PDOException('Unique constraint failed', 23000));
        
        // Replace in container
        $this->app->singleton(\App\Repositories\UserRepository::class, fn() => $mockRepo);

        $response = $this->app->handle(new Request('POST', '/api/auth/register', [], [], [], json_encode($payload)));

        $this->assertSame(400, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertSame('Registration could not be completed.', $body['message']);
    }
}
