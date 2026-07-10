<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Repositories\PostRepository;
use App\Database\MigrationRunner;
use PDO;

class PostVisibilityTest extends TestCase
{
    private PostRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $runner = new MigrationRunner($this->pdo);
        $runner->run(dirname(__DIR__, 2) . '/database/migrations');
        
        // Insert dummy users
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'User A', 'usera@example.com', 'hash')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (2, 'User B', 'userb@example.com', 'hash')");
        
        $this->repository = new PostRepository($this->pdo);
    }

    public function test_post_visibility_combinations(): void
    {
        // P1: User A draft
        $draftA = $this->repository->create(['user_id' => 1, 'title' => 'Draft A', 'slug' => 'da', 'content' => 'c', 'status' => 'draft']);
        
        // P2: User A published
        $pubA = $this->repository->create(['user_id' => 1, 'title' => 'Pub A', 'slug' => 'pa', 'content' => 'c', 'status' => 'published']);
        
        // P3: User A deleted draft
        $delDraftA = $this->repository->create(['user_id' => 1, 'title' => 'Del Draft A', 'slug' => 'dda', 'content' => 'c', 'status' => 'draft']);
        $this->repository->delete((int) $delDraftA['id']);
        
        // P4: User A deleted published
        $delPubA = $this->repository->create(['user_id' => 1, 'title' => 'Del Pub A', 'slug' => 'dpa', 'content' => 'c', 'status' => 'published']);
        $this->repository->delete((int) $delPubA['id']);
        
        // Viewer A
        $resultA = $this->repository->paginateForViewer(1);
        $titlesA = array_column($resultA['items'], 'title');
        
        $this->assertContains('Draft A', $titlesA, 'User A should see own draft');
        $this->assertContains('Pub A', $titlesA, 'User A should see own published post');
        $this->assertNotContains('Del Draft A', $titlesA, 'User A should not see own deleted draft');
        $this->assertNotContains('Del Pub A', $titlesA, 'User A should not see own deleted published post');
        
        // Viewer B
        $resultB = $this->repository->paginateForViewer(2);
        $titlesB = array_column($resultB['items'], 'title');
        
        $this->assertNotContains('Draft A', $titlesB, 'User B should not see User A draft');
        $this->assertContains('Pub A', $titlesB, 'User B should see User A published post');
        $this->assertNotContains('Del Pub A', $titlesB, 'User B should not see User A deleted published post');
    }

    public function test_pagination_and_sorting_combinations(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create([
                'user_id' => 1,
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'content' => "Content {$i}",
                'status' => 'published',
                'created_at' => "2026-07-08 12:0{$i}:00"
            ]);
        }

        // Pagination
        $res = $this->repository->paginateForViewer(1, 1, 2);
        $this->assertCount(2, $res['items']);
        $this->assertSame(1, $res['meta']['page']);
        $this->assertSame(2, $res['meta']['per_page']);
        $this->assertSame(5, $res['meta']['total']);
        
        // Page negatif dinormalisasi ke 1
        $resNeg = $this->repository->paginateForViewer(1, -5, 2);
        $this->assertSame(1, $resNeg['meta']['page']);
        
        // Page nol dinormalisasi ke 1
        $resZero = $this->repository->paginateForViewer(1, 0, 2);
        $this->assertSame(1, $resZero['meta']['page']);
        
        // Per_page terlalu besar dibatasi 100
        $resLarge = $this->repository->paginateForViewer(1, 1, 10000);
        $this->assertSame(100, $resLarge['meta']['per_page']);
        
        // Invalid sort field -> fallback to created_at
        $resInvSort = $this->repository->paginateForViewer(1, 1, 10, ['sort' => 'invalid_col']);
        $this->assertArrayHasKey('id', $resInvSort['items'][0]); // No SQL error
        
        // Invalid direction -> fallback to DESC
        $resInvDir = $this->repository->paginateForViewer(1, 1, 10, ['direction' => 'UP']);
        $this->assertArrayHasKey('id', $resInvDir['items'][0]); // No SQL error
    }

    public function test_sql_injection_payloads(): void
    {
        $this->repository->create(['user_id' => 1, 'title' => 'Safe Post', 'slug' => 'sp', 'content' => 'c', 'status' => 'published']);
        
        // If injection works, it might crash the query or return wrong data
        // 1. Sort injection
        $res1 = $this->repository->paginateForViewer(1, 1, 10, ['sort' => 'id desc; drop table posts']);
        $this->assertCount(1, $res1['items']); // Fallback to created_at
        
        // 2. Direction injection
        $res2 = $this->repository->paginateForViewer(1, 1, 10, ['direction' => 'asc; select sleep(5)']);
        $this->assertCount(1, $res2['items']); // Fallback to DESC
        
        // 3. Status injection
        $res3 = $this->repository->paginateForViewer(1, 1, 10, ['status' => "' OR 1=1 --"]);
        $this->assertCount(0, $res3['items']); // Searches for literal string
        
        // 4. Search injection
        $res4 = $this->repository->paginateForViewer(1, 1, 10, ['search' => "%' UNION SELECT"]);
        $this->assertCount(0, $res4['items']); // Searches for literal string
        
        // Ensure table still exists
        $count = $this->pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $this->assertGreaterThan(0, $count);
    }
}
