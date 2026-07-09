<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_correct_json(): void
    {
        /** @var \Intisari\Application $app */
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $request = new Request('GET', '/api/health');
        
        // $app->handle($request) returns a Lukman\Http\Response
        $response = $app->handle($request);
        
        $this->assertSame(200, $response->status());
        
        $body = json_decode($response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('OK', $body['message']);
        
        $this->assertSame('ok', $body['data']['status']);
        $this->assertArrayHasKey('app', $body['data']);
        $this->assertArrayHasKey('environment', $body['data']);
        $this->assertArrayHasKey('timestamp', $body['data']);
    }
}
