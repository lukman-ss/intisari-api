<?php

declare(strict_types=1);

namespace Tests\Feature;

class ComprehensiveAuthorizationMatrixTest extends SecurityRegressionTestCase
{
    private array $userA;
    private array $userB;
    
    private string $tokenA;
    private string $tokenB;
    private string $tokenInvalid = 'invalid_token_string';
    private string $tokenRevoked;

    private array $draftA;
    private array $publishedA;
    private array $deletedA;
    private array $draftB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userA = $this->createUser(['name' => 'User A']);
        $this->userB = $this->createUser(['name' => 'User B']);
        
        $tokenDataA = $this->createToken((int) $this->userA['id']);
        $this->tokenA = $tokenDataA['plain_token'];
        
        $tokenDataB = $this->createToken((int) $this->userB['id']);
        $this->tokenB = $tokenDataB['plain_token'];
        
        $tokenDataRevoked = $this->createToken((int) $this->userA['id']);
        $this->tokenRevoked = $tokenDataRevoked['plain_token'];
        $this->tokenService->revokeToken($this->tokenRevoked);
        
        $this->draftA = $this->createDraftPost((int) $this->userA['id'], ['title' => 'Draft A']);
        $this->publishedA = $this->createPublishedPost((int) $this->userA['id'], ['title' => 'Published A']);
        
        $this->deletedA = $this->createPublishedPost((int) $this->userA['id'], ['title' => 'Deleted A']);
        $this->postRepo->delete((int) $this->deletedA['id']);
        
        $this->draftB = $this->createDraftPost((int) $this->userB['id'], ['title' => 'Draft B']);
    }

    private function getDbTitle(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT title FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string) $res : null;
    }

    private function getDbCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    }

    public function test_list_operation_matrix(): void
    {
        // 1. Unauthenticated actors
        foreach ([null, $this->tokenInvalid, $this->tokenRevoked] as $token) {
            $res = $this->jsonRequest('GET', '/api/posts', [], $token);
            $this->assertSame(401, $res->status());
        }

        // 2. User A
        $resA = $this->jsonRequest('GET', '/api/posts', [], $this->tokenA);
        $this->assertSame(200, $resA->status());
        $titlesA = array_column(json_decode((string) $resA->content(), true)['data']['items'], 'title');
        
        $this->assertContains('Draft A', $titlesA);
        $this->assertContains('Published A', $titlesA);
        $this->assertNotContains('Deleted A', $titlesA);
        $this->assertNotContains('Draft B', $titlesA);

        // 3. User B
        $resB = $this->jsonRequest('GET', '/api/posts', [], $this->tokenB);
        $this->assertSame(200, $resB->status());
        $titlesB = array_column(json_decode((string) $resB->content(), true)['data']['items'], 'title');
        
        $this->assertNotContains('Draft A', $titlesB);
        $this->assertContains('Published A', $titlesB);
        $this->assertNotContains('Deleted A', $titlesB);
        $this->assertContains('Draft B', $titlesB);
    }

    public function test_show_operation_matrix(): void
    {
        $resources = [
            'Draft A' => [$this->draftA['id'], 200, 404],
            'Published A' => [$this->publishedA['id'], 200, 200],
            'Deleted A' => [$this->deletedA['id'], 404, 404],
            'Draft B' => [$this->draftB['id'], 404, 200],
        ];

        foreach ($resources as $name => [$id, $statusA, $statusB]) {
            // Unauthenticated
            foreach ([null, $this->tokenInvalid, $this->tokenRevoked] as $token) {
                $res = $this->jsonRequest('GET', "/api/posts/{$id}", [], $token);
                $this->assertSame(401, $res->status(), "Unauthenticated should be 401 for {$name}");
            }

            // User A
            $resA = $this->jsonRequest('GET', "/api/posts/{$id}", [], $this->tokenA);
            $this->assertSame($statusA, $resA->status(), "User A viewing {$name}");
            if ($statusA === 200) {
                $body = json_decode((string) $resA->content(), true)['data']['post'];
                $this->assertArrayNotHasKey('deleted_at', $body, 'Sensitive data leaked');
            }

            // User B
            $resB = $this->jsonRequest('GET', "/api/posts/{$id}", [], $this->tokenB);
            $this->assertSame($statusB, $resB->status(), "User B viewing {$name}");
        }
    }

    public function test_create_operation_matrix(): void
    {
        $initialCount = $this->getDbCount();

        // Unauthenticated
        foreach ([null, $this->tokenInvalid, $this->tokenRevoked] as $token) {
            $res = $this->jsonRequest('POST', '/api/posts', ['title' => 'Test', 'content' => 'Test'], $token);
            $this->assertSame(401, $res->status());
            $this->assertSame($initialCount, $this->getDbCount(), 'DB should not change on 401');
        }

        // User A
        $resA = $this->jsonRequest('POST', '/api/posts', ['title' => 'Post by A', 'content' => 'Test'], $this->tokenA);
        $this->assertSame(201, $resA->status());
        $this->assertSame($initialCount + 1, $this->getDbCount());
        $bodyA = json_decode((string) $resA->content(), true)['data']['post'];
        $this->assertSame((int) $this->userA['id'], (int) $bodyA['user_id']);

        // User B
        $resB = $this->jsonRequest('POST', '/api/posts', ['title' => 'Post by B', 'content' => 'Test'], $this->tokenB);
        $this->assertSame(201, $resB->status());
        $this->assertSame($initialCount + 2, $this->getDbCount());
        $bodyB = json_decode((string) $resB->content(), true)['data']['post'];
        $this->assertSame((int) $this->userB['id'], (int) $bodyB['user_id']);
    }

    public function test_update_operation_matrix(): void
    {
        $resources = [
            'Draft A' => [$this->draftA['id'], 200, 404, 'Draft A'],
            'Published A' => [$this->publishedA['id'], 200, 403, 'Published A'],
            'Deleted A' => [$this->deletedA['id'], 404, 404, 'Deleted A'],
            'Draft B' => [$this->draftB['id'], 404, 200, 'Draft B'],
        ];

        foreach ($resources as $name => [$id, $statusA, $statusB, $originalTitle]) {
            // Unauthenticated
            foreach ([null, $this->tokenInvalid, $this->tokenRevoked] as $token) {
                $res = $this->jsonRequest('PUT', "/api/posts/{$id}", ['title' => 'Hacked', 'content' => 'Hacked'], $token);
                $this->assertSame(401, $res->status());
                $this->assertSame($originalTitle, $this->getDbTitle((int) $id), "Title changed for {$name} on 401");
            }

            // User B trying to hack User A's posts (or updating own)
            $resB = $this->jsonRequest('PUT', "/api/posts/{$id}", ['title' => 'Hacked by B', 'content' => 'Hacked'], $this->tokenB);
            $this->assertSame($statusB, $resB->status());
            if ($statusB !== 200) {
                $this->assertSame($originalTitle, $this->getDbTitle((int) $id), "Title changed for {$name} on {$statusB}");
            }

            // User A updating own posts (or hacking B's)
            $resA = $this->jsonRequest('PUT', "/api/posts/{$id}", ['title' => 'Updated by A', 'content' => 'Updated'], $this->tokenA);
            $this->assertSame($statusA, $resA->status());
        }
    }

    public function test_delete_operation_matrix(): void
    {
        $resources = [
            'Draft A' => [$this->draftA['id'], 204, 404],
            'Published A' => [$this->publishedA['id'], 204, 403],
            'Deleted A' => [$this->deletedA['id'], 404, 404],
            'Draft B' => [$this->draftB['id'], 404, 204],
        ];

        foreach ($resources as $name => [$id, $statusA, $statusB]) {
            // Unauthenticated
            foreach ([null, $this->tokenInvalid, $this->tokenRevoked] as $token) {
                $res = $this->jsonRequest('DELETE', "/api/posts/{$id}", [], $token);
                $this->assertSame(401, $res->status());
            }
            
            // Check DB count unchanged
            $countBefore = $this->getDbCount();

            // User B
            $resB = $this->jsonRequest('DELETE', "/api/posts/{$id}", [], $this->tokenB);
            $this->assertSame($statusB, $resB->status());
            if ($statusB !== 204) {
                $this->assertSame($countBefore, $this->getDbCount());
            } else {
                // If B deletes successfully (Draft B), count decreases. We compensate for assertions below.
                $countBefore--;
            }

            // User A
            $resA = $this->jsonRequest('DELETE', "/api/posts/{$id}", [], $this->tokenA);
            $this->assertSame($statusA, $resA->status());
        }
    }
}
