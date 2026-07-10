<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\TokenService;

class TokenServiceAbilitiesTest extends TestCase
{
    private TokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenService($this->pdo);
        
        // Ensure a user exists to attach tokens to
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Test', 'test@example.com', 'hash')");
    }

    public function test_empty_array_remains_empty_and_not_wildcard(): void
    {
        $data = $this->service->createToken(1, 'empty', []);
        
        $this->assertSame([], $data['token']['abilities']);
    }

    public function test_duplicate_abilities_are_normalized(): void
    {
        $data = $this->service->createToken(1, 'dupes', ['posts.read', 'posts.read', 'tokens.read']);
        
        $this->assertSame(['posts.read', 'tokens.read'], $data['token']['abilities']);
    }

    public function test_rejects_non_string_abilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Abilities must be strings.');
        
        $this->service->createToken(1, 'invalid', ['posts.read', 123]);
    }
}
