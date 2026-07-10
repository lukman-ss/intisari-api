<?php

declare(strict_types=1);

namespace Tests\Feature;

class AuthRateLimitTest extends SecurityRegressionTestCase
{
    protected function tearDown(): void
    {
        // Clean up rate limit storage to not affect other tests
        $storagePath = dirname(__DIR__, 2) . '/storage/framework/rate-limit';
        if (is_dir($storagePath)) {
            $files = glob($storagePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_login_rate_limiting(): void
    {
        $payload = [
            'email' => 'test@example.com',
            'password' => 'secret123'
        ];

        // Login limit is 5 per 60 seconds
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->jsonRequest('POST', '/api/auth/login', $payload);
            // It might be 401 or 200, we don't care, just that it's NOT 429
            $this->assertNotSame(429, $res->status());
        }

        // 6th request should be rate limited
        $res = $this->jsonRequest('POST', '/api/auth/login', $payload);
        $this->assertSame(429, $res->status());
        
        $body = json_decode((string) $res->content(), true);
        $this->assertSame('RATE_LIMITED', $body['code']);
        
        $this->assertTrue($res->headers()->has('Retry-After'));
    }

    public function test_register_rate_limiting(): void
    {
        $payload = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret123'
        ];

        // Register limit is 3 per 3600 seconds
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->jsonRequest('POST', '/api/auth/register', $payload);
            $this->assertNotSame(429, $res->status());
        }

        // 4th request should be rate limited
        $res = $this->jsonRequest('POST', '/api/auth/register', $payload);
        $this->assertSame(429, $res->status());
        $this->assertTrue($res->headers()->has('Retry-After'));
    }

    public function test_rate_limit_buckets_are_isolated(): void
    {
        $loginPayload = ['email' => 'test@example.com', 'password' => 'secret'];
        $registerPayload = ['name' => 'Test', 'email' => 'test2@example.com', 'password' => 'secret'];

        // Exhaust register bucket (3 requests)
        for ($i = 1; $i <= 3; $i++) {
            $this->jsonRequest('POST', '/api/auth/register', $registerPayload);
        }
        
        $resRegisterBlock = $this->jsonRequest('POST', '/api/auth/register', $registerPayload);
        $this->assertSame(429, $resRegisterBlock->status(), 'Register should be blocked');

        // Login bucket should still be available
        $resLoginAllow = $this->jsonRequest('POST', '/api/auth/login', $loginPayload);
        $this->assertNotSame(429, $resLoginAllow->status(), 'Login should NOT be blocked by register bucket');
    }
}
