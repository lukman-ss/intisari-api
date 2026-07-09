<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\AuthManager;
use App\Middleware\AbilityMiddleware;
use App\Exceptions\ForbiddenException;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\Http\RequestHandlerInterface;
use Intisari\Application;

class AbilityTest extends TestCase
{
    private AuthManager $authManager;
    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = new AuthManager();
        $this->app = $this->createMock(Application::class);
        
        // Mock app() helper for middleware
        if (!function_exists('app')) {
            require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
        }
        
        // We will mock the app container dynamically in the test for middleware
    }

    public function test_can_returns_false_if_no_token(): void
    {
        $this->assertFalse($this->authManager->can('posts:read'));
    }

    public function test_can_returns_true_for_wildcard(): void
    {
        $this->authManager->setToken(['abilities' => ['*']]);
        
        $this->assertTrue($this->authManager->can('posts:read'));
        $this->assertTrue($this->authManager->can('posts:write'));
        $this->assertTrue($this->authManager->can('anything'));
    }

    public function test_can_returns_true_if_ability_exists(): void
    {
        $this->authManager->setToken(['abilities' => ['posts:read', 'posts:create']]);
        
        $this->assertTrue($this->authManager->can('posts:read'));
        $this->assertTrue($this->authManager->can('posts:create'));
    }

    public function test_can_returns_false_if_ability_missing(): void
    {
        $this->authManager->setToken(['abilities' => ['posts:read']]);
        
        $this->assertFalse($this->authManager->can('posts:delete'));
        $this->assertFalse($this->authManager->can('users:read'));
    }

    public function test_ability_middleware_passes_if_authorized(): void
    {
        $this->authManager->setToken(['abilities' => ['posts:read']]);
        
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(AuthManager::class, $this->authManager);
        
        $middleware = new AbilityMiddleware('posts:read');
        $request = new Request('GET', '/');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
                ->method('handle')
                ->willReturn(new Response('', 200));

        $response = $middleware->process($request, $handler);
        $this->assertSame(200, $response->status());
    }

    public function test_ability_middleware_throws_forbidden_if_unauthorized(): void
    {
        $this->authManager->setToken(['abilities' => ['posts:read']]);
        
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->instance(AuthManager::class, $this->authManager);
        
        $middleware = new AbilityMiddleware('posts:delete');
        $request = new Request('DELETE', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);
        
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Missing required ability: posts:delete');

        $middleware->process($request, $handler);
    }
}
