<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class InvalidJsonFeatureTest extends TestCase
{
    public function test_api_returns_400_for_invalid_json_payload(): void
    {
        $payload = '{ "title": "Test", incomplete_json ';
        
        $request = new Request('POST', '/api/auth/login', [], [], [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ], $payload);
        
        $response = $this->app->handle($request);
        
        $this->assertSame(400, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('INVALID_JSON', $body['code']);
        $this->assertSame('Invalid JSON payload', $body['message']);
    }
}
