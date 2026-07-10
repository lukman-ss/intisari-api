<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Middleware\RateLimitMiddleware;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\Http\RequestHandlerInterface;

class RateLimitConcurrencyTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = dirname(__DIR__, 2) . '/storage/framework/rate-limit';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            $files = glob($this->storagePath . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        parent::tearDown();
    }

    private function createRequest(string $ip = '127.0.0.1', string $uri = '/test', string $email = ''): Request
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        
        $body = '';
        if ($email !== '') {
            $body = json_encode(['email' => $email]);
        }
        
        return new Request('POST', $uri, [], [], ['Content-Type' => 'application/json'], $body);
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response('OK', 200);
            }
        };
    }

    public function test_key_isolation_two_emails_two_ips_two_routes(): void
    {
        $middleware = new RateLimitMiddleware(2, 60); // limit 2 per window
        $handler = $this->createHandler();

        $req1 = $this->createRequest('1.1.1.1', '/login', 'a@a.com');
        $req2 = $this->createRequest('1.1.1.1', '/login', 'b@b.com'); // Diff email
        $req3 = $this->createRequest('2.2.2.2', '/login', 'a@a.com'); // Diff IP
        $req4 = $this->createRequest('1.1.1.1', '/register', 'a@a.com'); // Diff Route

        // Exhaust req1
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $middleware->process($req1, $handler);
        $middleware->process($req1, $handler);
        $resBlocked = $middleware->process($req1, $handler);
        $this->assertSame(429, $resBlocked->status());

        // Req2 should pass (isolated by email)
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $res2 = $middleware->process($req2, $handler);
        $this->assertSame(200, $res2->status());

        // Req3 should pass (isolated by IP)
        $_SERVER['REMOTE_ADDR'] = '2.2.2.2';
        $res3 = $middleware->process($req3, $handler);
        $this->assertSame(200, $res3->status());

        // Req4 should pass (isolated by Route)
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $res4 = $middleware->process($req4, $handler);
        $this->assertSame(200, $res4->status());
    }

    public function test_corrupt_file_handling(): void
    {
        $middleware = new RateLimitMiddleware(1, 60);
        $handler = $this->createHandler();
        $req = $this->createRequest();

        // Trigger file creation
        $middleware->process($req, $handler);
        
        // Corrupt all files in directory
        $files = glob($this->storagePath . '/*');
        foreach ($files as $file) {
            file_put_contents($file, '{ invalid json');
        }

        // Should ignore corruption and start fresh (hit = 1, allowed)
        $res = $middleware->process($req, $handler);
        $this->assertSame(200, $res->status());
    }

    public function test_expired_window_resets_count(): void
    {
        $middleware = new RateLimitMiddleware(1, -1); // Expired immediately
        $handler = $this->createHandler();
        $req = $this->createRequest();

        // 1st request, limit is 1, so this uses up the limit
        $res1 = $middleware->process($req, $handler);
        $this->assertSame(200, $res1->status());

        // 2nd request, window expired (reset_at is in the past), so it starts fresh!
        $res2 = $middleware->process($req, $handler);
        $this->assertSame(200, $res2->status());
    }
}
