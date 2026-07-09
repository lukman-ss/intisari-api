<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;
use stdClass;

class ApiResponseTest extends TestCase
{
    public function test_success_response(): void
    {
        $response = ApiResponse::success(['id' => 1]);
        
        $this->assertSame(200, $response->status());
        $headers = array_map('strtolower', $response->headerLines());
        $this->assertContains('content-type: application/json', $headers);
        
        $body = json_decode($response->content(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('OK', $body['message']);
        $this->assertSame(['id' => 1], $body['data']);
    }

    public function test_success_response_empty_data_becomes_object(): void
    {
        $response = ApiResponse::success();
        
        $body = json_decode($response->content());
        $this->assertTrue($body->success);
        $this->assertEquals(new stdClass(), $body->data);
    }

    public function test_created_response(): void
    {
        $response = ApiResponse::created(['id' => 2]);
        
        $this->assertSame(201, $response->status());
        $body = json_decode($response->content(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Created', $body['message']);
        $this->assertSame(['id' => 2], $body['data']);
    }

    public function test_no_content_response(): void
    {
        $response = ApiResponse::noContent();
        
        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->content());
    }

    public function test_error_response(): void
    {
        $response = ApiResponse::error('Not found', 404, 'NOT_FOUND', ['id' => 'Invalid ID']);
        
        $this->assertSame(404, $response->status());
        $headers = array_map('strtolower', $response->headerLines());
        $this->assertContains('content-type: application/json', $headers);

        $body = json_decode($response->content(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Not found', $body['message']);
        $this->assertSame('NOT_FOUND', $body['code']);
        $this->assertSame(['id' => 'Invalid ID'], $body['errors']);
    }

    public function test_error_response_empty_errors_becomes_object(): void
    {
        $response = ApiResponse::error('Bad Request');
        
        $body = json_decode($response->content());
        $this->assertFalse($body->success);
        $this->assertSame('INTERNAL_ERROR', $body->code);
        $this->assertEquals(new stdClass(), $body->errors);
    }

    public function test_validation_response(): void
    {
        $response = ApiResponse::validation(['email' => 'Required']);
        
        $this->assertSame(422, $response->status());
        $body = json_decode($response->content(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Validation failed', $body['message']);
        $this->assertSame('VALIDATION_ERROR', $body['code']);
        $this->assertSame(['email' => 'Required'], $body['errors']);
    }

    public function test_paginated_response(): void
    {
        $response = ApiResponse::paginated(['item1'], ['page' => 1]);
        
        $this->assertSame(200, $response->status());
        $body = json_decode($response->content(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('OK', $body['message']);
        $this->assertSame(['item1'], $body['data']['items']);
        $this->assertSame(['page' => 1], $body['data']['meta']);
    }
}
