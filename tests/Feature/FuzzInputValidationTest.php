<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use App\Support\PasswordHasher;
use App\Database\ConnectionFactory;

class FuzzInputValidationTest extends TestCase
{
    private string $token;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $hasher = new PasswordHasher();
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Test User', 'test@example.com', $hasher->hash('password'), 1]);
        $userId = (int) $this->pdo->lastInsertId();
        
        $tokenRaw = bin2hex(random_bytes(20));
        $tokenHash = hash('sha256', $tokenRaw);
        
        $stmt = $this->pdo->prepare("INSERT INTO api_tokens (user_id, token_hash, name, abilities, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, 
            $tokenHash, 
            'Test Token', 
            json_encode(['*']), 
            date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);
        
        $tokenId = $this->pdo->lastInsertId();
        $this->token = $tokenId . '|' . $tokenRaw;
    }

    private function getAbnormalPayloads(): array
    {
        return [
            'string_when_array_expected' => "just a string",
            'array_when_string_expected' => ["name" => ["first", "last"], "email" => ["array@example.com"]],
            'object_when_scalar_expected' => ["email" => (object)["prop" => "value"], "password" => (object)["prop" => "value"]],
            'null_values' => ["name" => null, "email" => null, "password" => null],
            'boolean_values' => ["email" => true, "password" => false],
            'integer_extreme' => ["name" => 999999999999999999, "email" => -999999999999999999],
            'string_unicode' => ["email" => "test😀@example.com", "name" => "こんにちは"],
            'null_byte' => ["email" => "test\0@example.com", "password" => "pass\0word"],
            'oversized_string' => ["email" => "test@example.com", "password" => str_repeat("A", 100000)],
        ];
    }

    private function assertSafeResponse($response): void
    {
        $status = $response->status();
        $this->assertNotSame(500, $status, "Expected non-500 status code for abnormal input, got 500");
        
        $content = (string) $response->content();
        $this->assertStringNotContainsString('Stack trace:', $content);
        $this->assertStringNotContainsString('Fatal error:', $content);
        $this->assertStringNotContainsString('SQL syntax', $content);
        $this->assertStringNotContainsString('PDOException', $content);
    }

    private function checkEndpoint(string $method, string $uri, array $payload, bool $auth = false): void
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        // For arrays/objects we use standard json_encode
        $body = json_encode($payload);
        
        $request = new Request($method, $uri, [], [], $headers, $body);
        $response = $this->app->handle($request);
        
        $this->assertSafeResponse($response);
    }

    public function test_fuzz_login_endpoint(): void
    {
        foreach ($this->getAbnormalPayloads() as $payload) {
            $this->checkEndpoint('POST', '/api/auth/login', is_array($payload) ? $payload : ['data' => $payload]);
        }
    }

    public function test_fuzz_register_endpoint(): void
    {
        foreach ($this->getAbnormalPayloads() as $payload) {
            $this->checkEndpoint('POST', '/api/auth/register', is_array($payload) ? $payload : ['data' => $payload]);
        }
    }

    public function test_fuzz_token_creation_endpoint(): void
    {
        foreach ($this->getAbnormalPayloads() as $payload) {
            $this->checkEndpoint('POST', '/api/tokens', is_array($payload) ? $payload : ['data' => $payload], true);
        }
    }

    public function test_fuzz_post_create_endpoint(): void
    {
        foreach ($this->getAbnormalPayloads() as $payload) {
            $this->checkEndpoint('POST', '/api/posts', is_array($payload) ? $payload : ['data' => $payload], true);
        }
    }

    public function test_fuzz_post_update_endpoint(): void
    {
        // Insert a post first
        $stmt = $this->pdo->prepare("INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([1, 'Test Post', 'test-post', 'Content', 'published', $now, $now]);
        $postId = $this->pdo->lastInsertId();

        foreach ($this->getAbnormalPayloads() as $payload) {
            $this->checkEndpoint('PUT', "/api/posts/{$postId}", is_array($payload) ? $payload : ['data' => $payload], true);
        }
    }

    public function test_fuzz_post_listing_query(): void
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];
        
        // Fuzz query parameters directly instead of body
        $abnormalQueries = [
            '?page=invalid',
            '?page=-1',
            '?page=999999999999999999',
            '?per_page=999999999999999999',
            '?status[]=published',
            '?sort=(SELECT+1)',
            '?direction=UP',
            '?search=' . str_repeat('A', 10000),
            '?search=test%00byte',
        ];

        foreach ($abnormalQueries as $query) {
            $request = new Request('GET', "/api/posts" . $query, [], [], $headers);
            $response = $this->app->handle($request);
            $this->assertSafeResponse($response);
        }
    }

    public function test_fuzz_deeply_nested_arrays(): void
    {
        // We know JSON parser handles deeply nested via json_decode limit, 
        // but we'll try to trigger nested array via input
        $nested = [];
        $current = &$nested;
        for ($i = 0; $i < 50; $i++) {
            $current['child'] = [];
            $current = &$current['child'];
        }
        $current = "deep_value";

        $this->checkEndpoint('POST', '/api/auth/login', $nested);
    }

    public function test_fuzz_invalid_utf8(): void
    {
        $invalidUtf8 = "Invalid UTF-8 \x80\x81\x82";
        
        $request = new Request('POST', '/api/auth/login', [], [], ['Content-Type' => 'application/json'], '{"email":"' . $invalidUtf8 . '"}');
        $response = $this->app->handle($request);
        
        $this->assertSafeResponse($response);
    }

    public function test_fuzz_duplicate_json_keys(): void
    {
        $duplicateKeysJson = '{"email": "first@example.com", "email": "second@example.com", "password": "password"}';
        
        $request = new Request('POST', '/api/auth/login', [], [], ['Content-Type' => 'application/json'], $duplicateKeysJson);
        $response = $this->app->handle($request);
        
        $this->assertSafeResponse($response);
    }
}
