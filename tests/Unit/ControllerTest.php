<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Controllers\Controller;
use Lukman\Http\Request;

class DummyController extends Controller
{
    public function testInput(Request $request): array
    {
        return $this->input($request);
    }

    public function testQuery(Request $request): array
    {
        return $this->query($request);
    }
}

class ControllerTest extends TestCase
{
    public function test_input_parses_json_string(): void
    {
        $controller = new DummyController();
        
        $request = new Request('POST', '/', [], [], [], '{"name":"lukman","age":30}');
        $input = $controller->testInput($request);
        
        $this->assertSame(['name' => 'lukman', 'age' => 30], $input);
    }

    public function test_input_throws_exception_for_invalid_json(): void
    {
        $controller = new DummyController();
        
        $request = new Request('POST', '/', [], [], [], 'invalid json');
        
        $this->expectException(\App\Exceptions\InvalidJsonException::class);
        $controller->testInput($request);
    }

    public function test_input_returns_empty_array_for_empty_body(): void
    {
        $controller = new DummyController();
        
        $request = new Request('POST', '/', [], [], [], '');
        $input = $controller->testInput($request);
        
        $this->assertSame([], $input);
    }

    public function test_query_returns_query_parameters(): void
    {
        $controller = new DummyController();
        
        $request = new Request('GET', '/', ['page' => '2', 'sort' => 'desc'], [], [], '');
        $query = $controller->testQuery($request);
        
        $this->assertSame(['page' => '2', 'sort' => 'desc'], $query);
    }
}
