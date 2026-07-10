<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\TestCase as BaseTestCase;

class SecurityHeadersTest extends BaseTestCase
{
    public function test_successful_response_has_security_headers()
    {
        $request = new \Lukman\Http\Request('GET', '/api/health');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $this->assertEquals('nosniff', $response->headers()->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers()->get('X-Frame-Options'));
        $this->assertEquals('no-referrer', $response->headers()->get('Referrer-Policy'));
        $this->assertEquals('no-store, private', $response->headers()->get('Cache-Control'));
    }

    public function test_error_response_has_security_headers()
    {
        $request = new \Lukman\Http\Request('POST', '/api/auth/login', [], [], [], json_encode([]));
        $response = $this->app->handle($request);
        
        $this->assertEquals('nosniff', $response->headers()->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers()->get('X-Frame-Options'));
        $this->assertEquals('no-referrer', $response->headers()->get('Referrer-Policy'));
        $this->assertEquals('no-store, private', $response->headers()->get('Cache-Control'));
    }
    
    public function test_not_found_response_has_security_headers()
    {
        $request = new \Lukman\Http\Request('GET', '/api/does-not-exist');
        $response = $this->app->handle($request);
        
        $this->assertEquals('nosniff', $response->headers()->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers()->get('X-Frame-Options'));
        $this->assertEquals('no-referrer', $response->headers()->get('Referrer-Policy'));
        $this->assertEquals('no-store, private', $response->headers()->get('Cache-Control'));
    }
}
