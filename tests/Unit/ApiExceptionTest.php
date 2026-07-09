<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Exceptions\ApiException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InvalidJsonException;
use App\Support\Handler;
use Lukman\Http\Response;
use Lukman\Container\Container;
use Intisari\Application;

class ApiExceptionTest extends TestCase
{
    public function test_api_exception_has_default_values(): void
    {
        $exception = new ApiException();
        
        $this->assertSame('Internal server error', $exception->getMessage());
        $this->assertSame(500, $exception->getStatusCode());
        $this->assertSame('INTERNAL_ERROR', $exception->getErrorCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function test_not_found_exception(): void
    {
        $exception = new NotFoundException();
        
        $this->assertSame('Resource not found', $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('NOT_FOUND', $exception->getErrorCode());
    }

    public function test_forbidden_exception(): void
    {
        $exception = new ForbiddenException();
        
        $this->assertSame('Forbidden', $exception->getMessage());
        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame('FORBIDDEN', $exception->getErrorCode());
    }

    public function test_invalid_json_exception(): void
    {
        $exception = new InvalidJsonException();
        
        $this->assertSame('Invalid JSON payload', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('INVALID_JSON', $exception->getErrorCode());
    }

    public function test_handler_converts_api_exception_to_json_response(): void
    {
        // Mock Application to initialize Handler
        $app = $this->createMock(Application::class);
        $handler = new Handler($app);
        
        // Setup $_SERVER for API request
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $exception = new NotFoundException('User not found', ['id' => 'ID is invalid']);
        
        /** @var Response $response */
        $response = $handler->render($exception);
        
        $this->assertSame(404, $response->status());
        $body = json_decode($response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('User not found', $body['message']);
        $this->assertSame('NOT_FOUND', $body['code']);
        $this->assertSame(['id' => 'ID is invalid'], $body['errors']);
    }
}
