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
        $this->assertArrayNotHasKey('debug', (array) $body['errors']);
    }

    public function test_api_500_with_debug_true_exposes_debug_info(): void
    {
        putenv('APP_ENV=local');
        putenv('APP_DEBUG=true');
        
        \App\Support\Logger::setRequestId('req-12345');

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
        $this->assertArrayHasKey('file', $body['errors']['debug']);
        $this->assertArrayHasKey('trace', $body['errors']['debug']);
        $this->assertIsString($body['errors']['request_id']);
        $this->assertNotEmpty($body['errors']['request_id']);
        
        putenv('APP_ENV=testing');
        \App\Support\Logger::setRequestId(null);
    }

    public function test_api_500_production_hides_debug_info(): void
    {
        putenv('APP_ENV=production');
        putenv('APP_DEBUG=true');
        \App\Support\Logger::setRequestId('req-999');

        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $app->router()->get('/api/fail-prod', function () {
            throw new \Exception('Secret db failure');
        });

        $_SERVER['REQUEST_URI'] = '/api/fail-prod';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request('GET', '/api/fail-prod');
        $app->debug(true);
        
        $response = $app->handle($request);
        $body = json_decode($response->content(), true);
        
        $this->assertSame(500, $response->status());
        $this->assertArrayNotHasKey('debug', (array) $body['errors']);
        $this->assertStringNotContainsString('Secret db failure', $response->content());
        $this->assertIsString($body['errors']['request_id']);
        $this->assertNotEmpty($body['errors']['request_id']);
        
        putenv('APP_ENV=testing');
        \App\Support\Logger::setRequestId(null);
    }

    public function test_validation_exception_returns_422(): void
    {
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $app->router()->get('/api/val-fail', function () {
            throw new \App\Exceptions\ApiValidationException(['field' => ['Bad field']]);
        });

        $_SERVER['REQUEST_URI'] = '/api/val-fail';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request('GET', '/api/val-fail');
        
        $response = $app->handle($request);
        $body = json_decode($response->content(), true);
        
        $this->assertSame(422, $response->status());
        $this->assertSame(['Bad field'], $body['errors']['field']);
    }
}
