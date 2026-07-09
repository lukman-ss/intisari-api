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
        
        $tokenData = $this->tokenService->createToken((int) $this->user['id'], 'mobile', ['posts:read']);
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
}
