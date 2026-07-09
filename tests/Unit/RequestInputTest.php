<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\RequestInput;
use Lukman\Http\Request;
use RunTimeException;

class RequestInputTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_json_returns_empty_array_for_empty_body(): void
    {
        $request = new Request('POST', '/', [], [], [], '');
        
        $result = RequestInput::json($request);
        
        $this->assertSame([], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_json_parses_valid_json(): void
    {
        $request = new Request('POST', '/', [], [], [], '{"foo":"bar"}');
        
        $result = RequestInput::json($request);
        
        $this->assertSame(['foo' => 'bar'], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_all_merges_query_and_json(): void
    {
        $request = Request::capture(
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_CONTENT_TYPE' => 'application/json'],
            query: ['page' => '1'],
            body: '{"foo":"bar"}'
        );
        
        $result = RequestInput::all($request);
        
        $this->assertSame(['page' => '1', 'foo' => 'bar'], $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_json_outputs_400_and_exits_on_invalid_json(): void
    {
        $request = new Request('POST', '/', [], [], [], 'invalid json');
        
        $this->expectException(\App\Exceptions\InvalidJsonException::class);
        $this->expectExceptionMessage('Invalid JSON payload');
        
        RequestInput::json($request);
    }
}
