<?php

declare(strict_types=1);

namespace Tests\Feature;

class PaginationAbuseTest extends SecurityRegressionTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = $this->createUser(['name' => 'User Pagination']);
        $this->token = $this->createToken((int) $user['id'])['plain_token'];
        
        for ($i = 1; $i <= 5; $i++) {
            $this->createPublishedPost((int) $user['id']);
        }
    }

    public function test_pagination_abuse_page_negative(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['page' => -1], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        // Normalized to 1
        $this->assertSame(1, $body['data']['meta']['page']);
    }

    public function test_pagination_abuse_page_zero(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['page' => 0], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertSame(1, $body['data']['meta']['page']);
    }

    public function test_pagination_abuse_page_non_numeric(): void
    {
        // Should not throw PHP warning
        $response = $this->jsonRequest('GET', '/api/posts', ['page' => 'abc'], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertSame(1, $body['data']['meta']['page']);
    }

    public function test_pagination_abuse_per_page_negative(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['per_page' => -10], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        // Normalized to 1 (max(1, min(100, -10)) == 1)
        $this->assertSame(1, $body['data']['meta']['per_page']);
    }

    public function test_pagination_abuse_per_page_zero(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['per_page' => 0], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertSame(1, $body['data']['meta']['per_page']);
    }

    public function test_pagination_abuse_per_page_huge(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['per_page' => 1000000], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        // Capped at 100
        $this->assertSame(100, $body['data']['meta']['per_page']);
    }

    public function test_pagination_abuse_array_input(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts', ['per_page' => [10]], $this->token);
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        // Normalized to default 15
        $this->assertSame(15, $body['data']['meta']['per_page']);
    }
}
