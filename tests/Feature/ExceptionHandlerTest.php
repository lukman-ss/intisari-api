<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class ExceptionHandlerTest extends TestCase
{
    public function test_api_404_returns_json(): void
    {
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $_SERVER['REQUEST_URI'] = '/api/not-found-endpoint';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request('GET', '/api/not-found-endpoint');
        
        // Disable debug for this test to ensure no debug info is leaked
        $app->debug(false);
        
        $response = $app->handle($request);
        
        $this->assertSame(404, $response->status());
        
        $headers = array_map('strtolower', $response->headerLines());
        $this->assertContains('content-type: application/json', $headers);
        
        $body = json_decode($response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('Endpoint not found', $body['message']);
        
        // Make sure errors is an empty array/object and debug is not exposed
        $this->assertEmpty((array) $body['errors']);
        $this->assertArrayNotHasKey('debug', (array) $body['errors']);
    }

    public function test_api_500_with_debug_true_exposes_debug_info(): void
    {
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        // Register a failing route
        $app->router()->get('/api/fail', function () {
            throw new \Exception('Test exception');
        });

        $_SERVER['REQUEST_URI'] = '/api/fail';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request('GET', '/api/fail');
        
        // Enable debug for this test
        $app->debug(true);
        
        $response = $app->handle($request);
        
        $this->assertSame(500, $response->status());
        
        $body = json_decode($response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('Internal server error', $body['message']);
        
        $this->assertArrayHasKey('debug', (array) $body['errors']);
        $this->assertSame('Exception', $body['errors']['debug']['class']);
        $this->assertSame('Test exception', $body['errors']['debug']['message']);
    }
}
