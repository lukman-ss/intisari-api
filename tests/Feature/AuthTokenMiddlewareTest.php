<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Intisari\Application;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Support\TokenService;
use App\Support\AuthManager;
use App\Repositories\UserRepository;

class AuthTokenMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup a dummy route with auth middleware
        $router = $this->app->router();
        
        $router->get('/api/protected', function(Request $request) {
            $authManager = app(\App\Support\AuthManager::class);
            return \App\Support\ApiResponse::success([
                'user' => $authManager->user()
            ]);
        })->middleware(\App\Middleware\AuthTokenMiddleware::class);
    }

    public function test_rejects_missing_token(): void
    {
        $request = new Request('GET', '/api/protected');
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Unauthenticated', $body['message']);
    }

    public function test_rejects_invalid_token(): void
    {
        $request = new Request('GET', '/api/protected', [], [], ['Authorization' => 'Bearer invalid_token']);
        $response = $this->app->handle($request);
        
        $this->assertSame(401, $response->status());
    }

    public function test_accepts_valid_token_and_sets_user(): void
    {
        $userRepo = new UserRepository($this->pdo);
        $user = $userRepo->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'secret'
        ]);
        
        $tokenService = new TokenService($this->pdo);
        $tokenData = $tokenService->createToken((int) $user['id']);
        
        $request = new Request('GET', '/api/protected', [], [], ['Authorization' => 'Bearer ' . $tokenData['plain_token']]);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Admin', $body['data']['user']['name']);
    }
}
