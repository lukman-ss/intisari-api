<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use App\Support\RequestValidator;

class ApiValidationExceptionTest extends TestCase
{
    public function test_api_validation_exception_returns_422_json_with_errors(): void
    {
        $handler = new \App\Support\Handler();
        
        $_SERVER['REQUEST_URI'] = '/api/dummy-validation';
        
        $exception = new \App\Exceptions\ApiValidationException(
            ['email' => ['The email field is required.']],
            'Validation failed'
        );
        
        $response = $handler->render($exception);
        
        $this->assertSame(422, $response->status());
        
        $body = json_decode($response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertSame('Validation failed', $body['message']);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertSame('The email field is required.', $body['errors']['email'][0]);
    }
}
