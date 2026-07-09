<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class CorsTest extends TestCase
{
    public function test_it_handles_cors_options_request(): void
    {
        $request = new Request('OPTIONS', '/api/posts', [], [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST'
        ], '');
        
        $response = $this->app->handle($request);
        
        // Wait, what does Intisari or the app return? Usually 200 or 204.
        $this->assertTrue(in_array($response->status(), [200, 204]), 'CORS OPTIONS should return 200 or 204');
        
        $headers = array_map('strtolower', $response->headerLines());
        
        $hasAccessControl = false;
        foreach ($headers as $header) {
            if (str_contains($header, 'access-control-allow-origin')) {
                $hasAccessControl = true;
                break;
            }
        }
        
        $this->assertTrue($hasAccessControl, 'Response should contain Access-Control-Allow-Origin header');
    }
}
