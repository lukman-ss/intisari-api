<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class CorsTest extends TestCase
{
    public function test_it_allows_exact_origin_match(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        putenv('CORS_SUPPORTS_CREDENTIALS=false');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://example.com',
        ], '');
        
        $response = $app->handle($request);
        
        $this->assertSame(204, $response->status());
        $this->assertSame('https://example.com', $response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->headers()->get('Vary'));
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Credentials'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_rejects_wrong_scheme(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'http://example.com', // http instead of https
        ], '');
        
        $response = $app->handle($request);
        $this->assertSame(204, $response->status()); // OPTIONS still returns 204
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_rejects_wrong_port(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com:8080');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://example.com:8081',
        ], '');
        
        $response = $app->handle($request);
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_rejects_subdomain_attack(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://sub.example.com',
        ], '');
        
        $response = $app->handle($request);
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_rejects_suffix_attack(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://example.com.evil.test',
        ], '');
        
        $response = $app->handle($request);
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_parses_comma_separated_origins_with_spaces(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://foo.com ,  https://bar.com  ,');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('GET', '/api/posts', [], [], [
            'Origin' => 'https://bar.com',
        ], '');
        
        $response = $app->handle($request);
        
        $this->assertSame('https://bar.com', $response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->headers()->get('Vary'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_it_handles_credentials_active(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://frontend.com');
        putenv('CORS_SUPPORTS_CREDENTIALS=true');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('GET', '/api/posts', [], [], [
            'Origin' => 'https://frontend.com',
        ], '');
        
        $response = $app->handle($request);
        $this->assertSame('https://frontend.com', $response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers()->get('Access-Control-Allow-Credentials'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
        putenv('CORS_SUPPORTS_CREDENTIALS=');
    }

    public function test_it_never_combines_wildcard_with_credentials(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=*');
        putenv('CORS_SUPPORTS_CREDENTIALS=true');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('GET', '/api/posts', [], [], [
            'Origin' => 'https://frontend.com',
        ], '');
        
        $response = $app->handle($request);
        
        $this->assertSame('*', $response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Credentials')); // Must be empty
        $this->assertEmpty($response->headers()->get('Vary')); // No Vary: Origin for wildcard
        
        putenv('CORS_ALLOWED_ORIGINS=');
        putenv('CORS_SUPPORTS_CREDENTIALS=');
    }

    public function test_it_does_not_add_headers_if_no_origin(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('GET', '/api/posts', [], [], [], ''); // No Origin header
        
        $response = $app->handle($request);
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_options_preflight_has_max_age(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://example.com',
        ], '');
        
        $response = $app->handle($request);
        
        $this->assertSame(204, $response->status());
        $this->assertSame('86400', $response->headers()->get('Access-Control-Max-Age'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    public function test_malicious_options_preflight_is_rejected(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $app = require __DIR__ . '/../../bootstrap/app.php';
        
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'https://evil.com',
        ], '');
        
        $response = $app->handle($request);
        
        $this->assertSame(204, $response->status());
        $this->assertEmpty($response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertEmpty($response->headers()->get('Access-Control-Max-Age'));
        
        putenv('CORS_ALLOWED_ORIGINS=');
    }
}
