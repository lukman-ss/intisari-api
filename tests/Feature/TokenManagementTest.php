<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use App\Support\TokenService;
use App\Repositories\UserRepository;

class TokenManagementTest extends TestCase
{
    private array $user1;
    private array $user2;
    private string $token1;
    private string $token2;
    private TokenService $tokenService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $pdo = $this->pdo;
        $userRepo = new UserRepository($pdo);
        $this->tokenService = new TokenService($pdo);
        
        $this->user1 = $userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password_hash' => 'hash1'
        ]);
        
        $this->user2 = $userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password_hash' => 'hash2'
        ]);
        
        $tokenData1 = $this->tokenService->createToken((int) $this->user1['id'], 'test-token-1', ['tokens.read', 'tokens.create', 'tokens.revoke', 'posts.read']);
        $this->token1 = $tokenData1['plain_token'];
        
        $tokenData2 = $this->tokenService->createToken((int) $this->user2['id'], 'test-token-2', ['tokens.read', 'tokens.create', 'tokens.revoke', 'posts.read']);
        $this->token2 = $tokenData2['plain_token'];
    }

    public function test_user_can_list_own_tokens(): void
    {
        $request = new Request('GET', '/api/tokens', [], [], ['Authorization' => 'Bearer ' . $this->token1]);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertTrue($body['success']);
        
        $tokens = $body['data']['tokens'];
        $this->assertCount(1, $tokens);
        $this->assertSame('test-token-1', $tokens[0]['name']);
        
        // Ensure token_hash is not leaked
        $this->assertArrayNotHasKey('token_hash', $tokens[0]);
    }

    public function test_user_can_create_new_token(): void
    {
        $payload = json_encode([
            'name' => 'mobile-app',
            'abilities' => ['posts.read']
        ]);
        
        $request = new Request('POST', '/api/tokens', [], [], [
            'Authorization' => 'Bearer ' . $this->token1,
            'Content-Type' => 'application/json'
        ], $payload);
        
        $response = $this->app->handle($request);
        
        $this->assertSame(201, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('plain_token', $body['data']);
        $this->assertSame('mobile-app', $body['data']['token']['name']);
        
        // Ensure token_hash is not leaked
        $this->assertArrayNotHasKey('token_hash', $body['data']['token']);
    }

    public function test_user_can_revoke_own_token(): void
    {
        // User1 creates a token to revoke
        $tokenData = $this->tokenService->createToken((int) $this->user1['id'], 'to-revoke');
        $tokenId = $tokenData['token']['id'];
        
        $request = new Request('DELETE', '/api/tokens/' . $tokenId, [], [], ['Authorization' => 'Bearer ' . $this->token1]);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        // Verify it's deleted
        $tokens = $this->tokenService->getUserTokens((int) $this->user1['id']);
        $this->assertCount(1, $tokens); // only token1 remains
    }

    public function test_user_cannot_revoke_others_token(): void
    {
        // Get user2's token id
        $tokens2 = $this->tokenService->getUserTokens((int) $this->user2['id']);
        $tokenId2 = $tokens2[0]['id'];
        
        // User1 tries to revoke User2's token
        $request = new Request('DELETE', '/api/tokens/' . $tokenId2, [], [], ['Authorization' => 'Bearer ' . $this->token1]);
        $response = $this->app->handle($request);
        
        $this->assertSame(404, $response->status());
        
        // Verify it's NOT deleted
        $tokens2_after = $this->tokenService->getUserTokens((int) $this->user2['id']);
        $this->assertCount(1, $tokens2_after);
    }
}
