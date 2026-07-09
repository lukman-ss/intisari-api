<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_it_generates_request_id_if_missing(): void
    {
        $request = new Request('GET', '/api/health', [], [], []);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $requestId = $response->headers()->get('X-Request-Id');
        if (is_array($requestId)) $requestId = $requestId[0] ?? '';
        $this->assertNotEmpty($requestId);
        $this->assertSame(32, strlen($requestId)); // bin2hex of 16 bytes = 32 chars
    }

    public function test_it_uses_provided_valid_request_id(): void
    {
        $validId = '1234567890abcdef1234567890abcdef'; // 32 chars
        
        $request = new Request('GET', '/api/health', [], [], [
            'X-Request-Id' => $validId
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $actualId = $response->headers()->get('X-Request-Id');
        if (is_array($actualId)) $actualId = $actualId[0] ?? '';
        $this->assertSame($validId, $actualId);
    }

    public function test_it_ignores_invalid_request_id(): void
    {
        $invalidId = 'short'; // Invalid format
        
        $request = new Request('GET', '/api/health', [], [], [
            'X-Request-Id' => $invalidId
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $requestId = $response->headers()->get('X-Request-Id');
        if (is_array($requestId)) $requestId = $requestId[0] ?? '';
        $this->assertNotEmpty($requestId);
        $this->assertNotSame($invalidId, $requestId);
        $this->assertSame(32, strlen($requestId));
    }
}
