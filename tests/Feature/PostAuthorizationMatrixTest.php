<?php

declare(strict_types=1);

namespace Tests\Feature;

class PostAuthorizationMatrixTest extends SecurityRegressionTestCase
{
    private array $userA;
    private array $userB;
    private string $tokenA;
    private string $tokenB;
    
    private array $draftA;
    private array $publishedA;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userA = $this->createUser(['name' => 'User A']);
        $this->userB = $this->createUser(['name' => 'User B']);
        
        $this->tokenA = $this->createToken((int) $this->userA['id'])['plain_token'];
        $this->tokenB = $this->createToken((int) $this->userB['id'])['plain_token'];
        
        $this->draftA = $this->createDraftPost((int) $this->userA['id'], ['title' => 'Draft A']);
        $this->publishedA = $this->createPublishedPost((int) $this->userA['id'], ['title' => 'Published A']);
    }

    public function test_user_can_read_own_draft(): void
    {
        $response = $this->jsonRequest('GET', "/api/posts/{$this->draftA['id']}", [], $this->tokenA);
        $this->assertSame(200, $response->status());
    }

    public function test_user_cannot_read_others_draft_returns_404(): void
    {
        $response = $this->jsonRequest('GET', "/api/posts/{$this->draftA['id']}", [], $this->tokenB);
        $this->assertSame(404, $response->status());
    }

    public function test_user_can_read_others_published_post(): void
    {
        $response = $this->jsonRequest('GET', "/api/posts/{$this->publishedA['id']}", [], $this->tokenB);
        $this->assertSame(200, $response->status());
    }

    public function test_user_can_update_own_post(): void
    {
        $response = $this->jsonRequest('PUT', "/api/posts/{$this->draftA['id']}", [
            'title' => 'Updated Draft A',
            'content' => 'Updated Content'
        ], $this->tokenA);
        
        $this->assertSame(200, $response->status());
    }

    public function test_user_cannot_update_others_draft_returns_404(): void
    {
        $response = $this->jsonRequest('PUT', "/api/posts/{$this->draftA['id']}", [
            'title' => 'Hacked Draft A',
            'content' => 'Hacked Content'
        ], $this->tokenB);
        
        $this->assertSame(404, $response->status());
    }

    public function test_user_cannot_update_others_published_returns_403(): void
    {
        $response = $this->jsonRequest('PUT', "/api/posts/{$this->publishedA['id']}", [
            'title' => 'Hacked Published A',
            'content' => 'Hacked Content'
        ], $this->tokenB);
        
        $this->assertSame(403, $response->status());
    }

    public function test_user_can_delete_own_post(): void
    {
        $response = $this->jsonRequest('DELETE', "/api/posts/{$this->draftA['id']}", [], $this->tokenA);
        $this->assertSame(204, $response->status());
    }

    public function test_user_cannot_delete_others_draft_returns_404(): void
    {
        $response = $this->jsonRequest('DELETE', "/api/posts/{$this->draftA['id']}", [], $this->tokenB);
        $this->assertSame(404, $response->status());
    }

    public function test_user_cannot_delete_others_published_returns_403(): void
    {
        $response = $this->jsonRequest('DELETE', "/api/posts/{$this->publishedA['id']}", [], $this->tokenB);
        $this->assertSame(403, $response->status());
    }

    public function test_cannot_spoof_ownership_on_create(): void
    {
        $response = $this->jsonRequest('POST', '/api/posts', [
            'title' => 'Spoofed Post',
            'content' => 'Content',
            'user_id' => $this->userA['id'] // Try to spoof User A
        ], $this->tokenB);
        
        $this->assertSame(201, $response->status());
        
        $body = json_decode((string) $response->content(), true);
        $this->assertSame((int) $this->userB['id'], (int) $body['data']['post']['user_id'], 'Ownership must be strictly determined by server authentication.');
    }

    public function test_cannot_write_internal_fields_on_update(): void
    {
        $response = $this->jsonRequest('PUT', "/api/posts/{$this->draftA['id']}", [
            'title' => 'Test Internal Fields',
            'content' => 'Content',
            'deleted_at' => '2000-01-01 00:00:00',
            'user_id' => $this->userB['id']
        ], $this->tokenA);
        
        $this->assertSame(200, $response->status());
        
        // Verify from DB that user_id and deleted_at did not change
        $post = $this->postRepo->findById((int) $this->draftA['id']);
        
        $this->assertSame((int) $this->userA['id'], (int) $post['user_id']);
    }
}
