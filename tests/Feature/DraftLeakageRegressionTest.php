<?php

declare(strict_types=1);

namespace Tests\Feature;

class DraftLeakageRegressionTest extends SecurityRegressionTestCase
{
    public function test_drafts_should_not_leak_to_other_users_via_collection_endpoint(): void
    {
        // 1. Buat User A
        $userA = $this->createUser(['name' => 'User A']);
        
        // 2. Buat draft milik User A
        $draftA = $this->createDraftPost((int) $userA['id'], ['title' => 'User A Draft']);
        
        // 3. Buat published post milik User A
        $publishedA = $this->createPublishedPost((int) $userA['id'], ['title' => 'User A Published']);

        // Tambahan: Buat soft-deleted post milik User A (should not be visible)
        $deletedPostA = $this->createPublishedPost((int) $userA['id'], ['title' => 'User A Deleted']);
        $this->postRepo->delete((int) $deletedPostA['id']);
        
        // 4. Buat User B
        $userB = $this->createUser(['name' => 'User B']);
        
        // Buat draft milik User B sendiri
        $draftB = $this->createDraftPost((int) $userB['id'], ['title' => 'User B Draft']);
        
        // 5. Login sebagai User B
        $tokenDataB = $this->createToken((int) $userB['id']);
        $tokenB = $tokenDataB['plain_token'];
        
        // 6. Request GET /api/posts
        $response = $this->jsonRequest('GET', '/api/posts', [], $tokenB);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        
        $items = $body['data']['items'];
        $titles = array_column($items, 'title');
        
        // 7. Assertion pembanding
        // Published post User A boleh terlihat oleh User B.
        $this->assertContains('User A Published', $titles, 'Published posts should be visible.');
        
        // Draft User A tidak boleh terlihat oleh User B.
        $this->assertNotContains('User A Draft', $titles, 'Draft posts of other users should not be visible.');
        
        // Draft User B sendiri boleh terlihat oleh User B.
        $this->assertContains('User B Draft', $titles, 'Own draft posts should be visible.');
        
        // Soft-deleted post tidak boleh terlihat.
        $this->assertNotContains('User A Deleted', $titles, 'Soft-deleted posts should not be visible.');
        
        // Response pagination tetap valid.
        $this->assertArrayHasKey('meta', $body['data']);
        $this->assertArrayHasKey('total', $body['data']['meta']);
        
        // Jumlah total seharusnya hanya Published A dan Draft B (2 posts).
        $this->assertCount(2, $items);
    }

    public function test_query_manipulation_does_not_override_visibility(): void
    {
        $userA = $this->createUser(['name' => 'User A']);
        $draftA = $this->createDraftPost((int) $userA['id'], ['title' => 'User A Draft']);
        
        $userB = $this->createUser(['name' => 'User B']);
        $tokenDataB = $this->createToken((int) $userB['id']);
        $tokenB = $tokenDataB['plain_token'];
        
        // Attempt to spoof viewer_id, user_id, and filter by draft
        $response = $this->jsonRequest('GET', '/api/posts', [
            'viewer_id' => $userA['id'],
            'user_id' => $userA['id'],
            'status' => 'draft'
        ], $tokenB);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $items = $body['data']['items'];
        $titles = array_column($items, 'title');
        
        // Draft A should NOT be visible despite trying to view as User A and asking for drafts
        $this->assertNotContains('User A Draft', $titles);
        $this->assertCount(0, $items);
    }
}
