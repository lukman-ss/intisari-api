<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Support\RequestInput;
use Lukman\Http\Request;
use App\Exceptions\ApiException;
use App\Exceptions\InvalidJsonException;

class RequestInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset limit
        RequestInput::setMaxBodySize(1048576);
    }

    public function test_it_parses_small_body_successfully(): void
    {
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json'
        ], '{"title": "Test"}');
        
        $json = RequestInput::json($request);
        $this->assertSame(['title' => 'Test'], $json);
    }

    public function test_it_rejects_oversized_content_length(): void
    {
        RequestInput::setMaxBodySize(100); // 100 bytes limit
        
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json',
            'Content-Length' => '101'
        ], ''); // body not even read yet
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Payload Too Large');
        
        RequestInput::json($request);
    }

    public function test_it_rejects_oversized_body_string(): void
    {
        RequestInput::setMaxBodySize(10); // 10 bytes limit
        
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json'
        ], '{"a": "1234567890"}'); // > 10 bytes
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Payload Too Large');
        
        RequestInput::json($request);
    }

    public function test_it_rejects_malformed_json(): void
    {
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json'
        ], '{"title": "Test"'); // Missing closing brace
        
        $this->expectException(InvalidJsonException::class);
        
        RequestInput::json($request);
    }

    public function test_it_rejects_deeply_nested_json(): void
    {
        // Default max depth is 128
        $nested = '{"a":1}';
        for ($i = 0; $i < 130; $i++) {
            $nested = '{"a":' . $nested . '}';
        }
        
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json'
        ], $nested);
        
        $this->expectException(InvalidJsonException::class);
        
        RequestInput::json($request);
    }

    public function test_empty_body_returns_empty_array(): void
    {
        $request = new Request('POST', '/api/posts', [], [], [
            'Content-Type' => 'application/json'
        ], '');
        
        $json = RequestInput::json($request);
        $this->assertSame([], $json);
    }
}
