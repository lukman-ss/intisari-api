<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Middleware\RateLimitMiddleware;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\Http\RequestHandlerInterface;
use App\Support\ApiResponse;

class RateLimitMiddlewareTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = dirname(__DIR__, 2) . '/storage/framework/rate-limit';
        
        // Clean up storage before each test
        if (is_dir($this->storagePath)) {
            $files = glob($this->storagePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up storage after each test
        if (is_dir($this->storagePath)) {
            $files = glob($this->storagePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_it_allows_requests_under_limit_and_adds_headers(): void
    {
        $middleware = new RateLimitMiddleware(2, 60); // Limit 2 requests
        $request = new Request('GET', '/api/test');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->exactly(2))
                ->method('handle')
                ->willReturn(new Response('OK', 200));

        // First request
        $response1 = $middleware->process($request, $handler);
        $this->assertSame(200, $response1->status());
        $headers1 = $response1->headers()->all();
        $this->assertSame('2', $headers1['x-ratelimit-limit']);
        $this->assertSame('1', $headers1['x-ratelimit-remaining']);

        // Second request
        $response2 = $middleware->process($request, $handler);
        $this->assertSame(200, $response2->status());
        $headers2 = $response2->headers()->all();
        $this->assertSame('2', $headers2['x-ratelimit-limit']);
        $this->assertSame('0', $headers2['x-ratelimit-remaining']);
    }

    public function test_it_blocks_requests_over_limit_with_429(): void
    {
        $middleware = new RateLimitMiddleware(1, 60); // Limit 1 request
        $request = new Request('GET', '/api/test');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
                ->method('handle')
                ->willReturn(new Response('OK', 200));

        // First request (allowed)
        $response1 = $middleware->process($request, $handler);
        $this->assertSame(200, $response1->status());

        // Second request (blocked)
        $response2 = $middleware->process($request, $handler);
        
        $this->assertSame(429, $response2->status());
        $headers2 = $response2->headers()->all();
        
        $this->assertSame('1', $headers2['x-ratelimit-limit']);
        $this->assertSame('0', $headers2['x-ratelimit-remaining']);
        $this->assertArrayHasKey('retry-after', $headers2);
        
        $body = json_decode($response2->content(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Too many requests', $body['message']);
        $this->assertSame('RATE_LIMITED', $body['code']);
    }

    public function test_it_resets_after_window_expires(): void
    {
        $middleware = new RateLimitMiddleware(1, 1); // Limit 1 request, 1 sec window
        $request = new Request('GET', '/api/test');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->exactly(2))
                ->method('handle')
                ->willReturn(new Response('OK', 200));

        // First request (allowed)
        $middleware->process($request, $handler);

        // Sleep to let the window expire
        sleep(2);

        // Third request (should be allowed again)
        $response3 = $middleware->process($request, $handler);
        $this->assertSame(200, $response3->status());
        $headers3 = $response3->headers()->all();
        $this->assertSame('0', $headers3['x-ratelimit-remaining']); // Because limit is 1, remaining is 0
    }
}
