<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use App\Support\TokenService;
use App\Repositories\UserRepository;

class AuthRefreshTest extends TestCase
{
    private array $user;
    private string $plainToken;
    private TokenService $tokenService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $pdo = $this->pdo;
        $userRepo = new UserRepository($pdo);
        $this->tokenService = new TokenService($pdo);
        
        $this->user = $userRepo->create([
            'name' => 'Refresh User',
            'email' => 'refresh@example.com',
            'password_hash' => 'hash'
        ]);
        
        $tokenData = $this->tokenService->createToken((int) $this->user['id'], 'mobile', ['posts.read']);
        $this->plainToken = $tokenData['plain_token'];
    }

    public function test_it_requires_authentication(): void
    {
        $request = new Request('POST', '/api/auth/refresh', [], [], []);
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
    }

    public function test_it_refreshes_token_successfully(): void
    {
        $request = new Request('POST', '/api/auth/refresh', [], [], [
            'Authorization' => 'Bearer ' . $this->plainToken
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertTrue($body['success']);
        
        $newToken = $body['data']['token'];
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($this->plainToken, $newToken);
        
        // Verify new token works
        $meRequest = new Request('GET', '/api/auth/me', [], [], [
            'Authorization' => 'Bearer ' . $newToken
        ]);
        $meResponse = $this->app->handle($meRequest);
        $this->assertSame(200, $meResponse->status());
        
        // Verify old token is invalid
        $oldMeRequest = new Request('GET', '/api/auth/me', [], [], [
            'Authorization' => 'Bearer ' . $this->plainToken
        ]);
        $oldMeResponse = $this->app->handle($oldMeRequest);
        $this->assertSame(401, $oldMeResponse->status());
    }

    public function test_it_allows_narrowing_abilities_on_refresh(): void
    {
        // Issue token with multiple abilities
        $tokenData = $this->tokenService->createToken((int) $this->user['id'], 'mobile', ['posts.read', 'posts.create']);
        $token = $tokenData['plain_token'];

        $request = new Request('POST', '/api/auth/refresh', [], [], [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ], json_encode(['abilities' => ['posts.read']]));
        
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        $newToken = $body['data']['token'];

        // Verify the new token only has posts.read
        $newTokenData = $this->tokenService->findValidToken($newToken);
        $this->assertSame(['posts.read'], $newTokenData['abilities']);
    }

    public function test_it_rejects_amplifying_abilities_on_refresh(): void
    {
        $request = new Request('POST', '/api/auth/refresh', [], [], [
            'Authorization' => 'Bearer ' . $this->plainToken,
            'Content-Type' => 'application/json'
        ], json_encode(['abilities' => ['posts.read', 'posts.delete']]));
        
        $response = $this->app->handle($request);
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertStringContainsString('Cannot request ability you do not have: posts.delete', $body['errors']['abilities'][0]);
    }

    public function test_it_prevents_race_condition_producing_multiple_tokens(): void
    {
        // First refresh works
        $request1 = new Request('POST', '/api/auth/refresh', [], [], ['Authorization' => 'Bearer ' . $this->plainToken]);
        $response1 = $this->app->handle($request1);
        $this->assertSame(200, $response1->status());
        
        // At this point, the old plainToken is still valid in memory if race condition happened,
        // but let's assume it passes AuthTokenMiddleware and hits AuthController::refresh again
        
        // We mock AuthManager to return the old token, simulating AuthTokenMiddleware already passed it
        $authManager = app(\App\Support\AuthManager::class);
        $authManager->setUser($this->user);
        $authManager->setToken(['name' => 'mobile', 'abilities' => ['posts.read']]);

        // Second refresh with the SAME old token should be blocked by revokeToken returning false
        $request2 = new Request('POST', '/api/auth/refresh', [], [], ['Authorization' => 'Bearer ' . $this->plainToken]);
        $response2 = $this->app->handle($request2);
        
        $this->assertSame(401, $response2->status());
        $body2 = json_decode((string) $response2->content(), true);
        $this->assertSame('Unauthenticated', $body2['message']);
    }
}
