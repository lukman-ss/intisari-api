<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;

class ProblemDetailsTest extends TestCase
{
    public function test_it_uses_default_error_format(): void
    {
        putenv('API_ERROR_FORMAT=default');
        
        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode(['email' => 'invalid']));
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        
        $this->assertArrayHasKey('success', $body);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('code', $body);
        $this->assertArrayHasKey('errors', $body);
        $this->assertSame('VALIDATION_ERROR', $body['code']);
    }

    public function test_it_uses_problem_details_error_format(): void
    {
        putenv('API_ERROR_FORMAT=problem');
        
        $request = new Request('POST', '/api/auth/login', [], [], [], json_encode(['email' => 'invalid']));
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        
        $this->assertArrayNotHasKey('success', $body);
        $this->assertArrayNotHasKey('code', $body);
        
        $this->assertArrayHasKey('type', $body);
        $this->assertSame('about:blank', $body['type']);
        $this->assertArrayHasKey('title', $body);
        $this->assertSame('Validation failed', $body['title']);
        $this->assertArrayHasKey('status', $body);
        $this->assertSame(422, $body['status']);
        $this->assertArrayHasKey('detail', $body);
        $this->assertSame('Validation failed', $body['detail']);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('password', $body['errors']);
        
        // Reset to avoid affecting other tests
        putenv('API_ERROR_FORMAT');
    }
}
