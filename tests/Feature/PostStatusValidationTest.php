<?php

declare(strict_types=1);

namespace Tests\Feature;

class PostStatusValidationTest extends SecurityRegressionTestCase
{
    private array $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = $this->createUser();
        $this->token = $this->createToken((int) $this->user['id'])['plain_token'];
    }

    public function test_valid_statuses_are_accepted(): void
    {
        // 1. draft
        $resDraft = $this->jsonRequest('POST', '/api/posts', [
            'title' => 'Draft Post',
            'content' => 'Content',
            'status' => 'draft'
        ], $this->token);
        $this->assertSame(201, $resDraft->status());
        
        // 2. published
        $resPublished = $this->jsonRequest('POST', '/api/posts', [
            'title' => 'Published Post',
            'content' => 'Content',
            'status' => 'published'
        ], $this->token);
        $this->assertSame(201, $resPublished->status());
    }

    public function test_invalid_statuses_are_rejected_with_422(): void
    {
        $invalidStatuses = [
            'DRAFT',     // Uppercase
            'unknown',   // Arbitrary string
            null,        // Null
            [],          // Array
            ['draft'],   // Array with value
            new \stdClass(), // Object
            0,           // Number
            1            // Number
        ];

        foreach ($invalidStatuses as $status) {
            $res = $this->jsonRequest('POST', '/api/posts', [
                'title' => 'Title',
                'content' => 'Content',
                'status' => $status
            ], $this->token);

            $this->assertSame(422, $res->status(), "Status " . json_encode($status) . " should be rejected");
            
            $body = json_decode((string) $res->content(), true);
            $this->assertArrayHasKey('status', $body['errors']);
        }
    }
}
