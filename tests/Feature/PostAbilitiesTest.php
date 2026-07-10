<?php

declare(strict_types=1);

namespace Tests\Feature;

class PostAbilitiesTest extends SecurityRegressionTestCase
{
    private array $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

    public function test_read_only_token_can_read_but_not_create(): void
    {
        $tokenData = $this->tokenService->createToken((int)$this->user['id'], 'readonly', ['posts.read']);
        $token = $tokenData['plain_token'];

        // Can read
        $res = $this->jsonRequest('GET', '/api/posts', [], $token);
        $this->assertSame(200, $res->status());

        // Cannot create
        $res = $this->jsonRequest('POST', '/api/posts', [
            'title' => 'Test',
            'content' => 'Test content',
            'status' => 'draft'
        ], $token);
        
        // Missing ability -> 403
        $this->assertSame(403, $res->status());
    }

    public function test_write_only_token_can_create_but_not_read(): void
    {
        $tokenData = $this->tokenService->createToken((int)$this->user['id'], 'writeonly', ['posts.create']);
        $token = $tokenData['plain_token'];

        // Cannot read
        $res = $this->jsonRequest('GET', '/api/posts', [], $token);
        $this->assertSame(403, $res->status());

        // Can create
        $res = $this->jsonRequest('POST', '/api/posts', [
            'title' => 'Test',
            'content' => 'Test content',
            'status' => 'draft'
        ], $token);
        
        $this->assertSame(201, $res->status());
    }

    public function test_missing_or_invalid_token_is_401(): void
    {
        $res = $this->jsonRequest('GET', '/api/posts', [], null);
        $this->assertSame(401, $res->status());
        
        $res = $this->jsonRequest('GET', '/api/posts', [], 'invalid-token');
        $this->assertSame(401, $res->status());
    }
}
