<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class MiddlewareTest extends TestCase
{
    public function test_options_health_returns_cors_headers(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=*');
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $_SERVER['REQUEST_URI'] = '/api/health';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
        
        $request = new Request('OPTIONS', '/api/health');
        
        $response = $app->handle($request);
        
        $this->assertSame(204, $response->status());
        
        $headers = array_map('strtolower', $response->headerLines());
        
        $this->assertContains('access-control-allow-origin: *', $headers);
        $this->assertContains('access-control-allow-methods: get, post, put, patch, delete, options', $headers);
        putenv('CORS_ALLOWED_ORIGINS=');
    }
    
    public function test_api_health_returns_security_headers(): void
    {
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $request = new Request('GET', '/api/health');
        
        $response = $app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $headers = array_map('strtolower', $response->headerLines());
        
        $this->assertContains('x-content-type-options: nosniff', $headers);
        $this->assertContains('x-frame-options: deny', $headers);
        $this->assertContains('referrer-policy: no-referrer', $headers);
        $this->assertContains('content-type: application/json', $headers);
    }
}
