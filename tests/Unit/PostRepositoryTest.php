<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Repositories\PostRepository;
use App\Database\MigrationRunner;
use PDO;

class PostRepositoryTest extends TestCase
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
        
        // Insert a dummy user
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Test', 'test@example.com', 'hash')");
        
        $this->repository = new PostRepository($this->pdo);
    }

    public function test_it_creates_a_post(): void
    {
        $data = [
            'user_id' => 1,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => 'This is my first post',
            'status' => 'published'
        ];

        $post = $this->repository->create($data);

        $this->assertIsArray($post);
        $this->assertArrayHasKey('id', $post);
        $this->assertSame('Hello World', $post['title']);
        $this->assertSame('published', $post['status']);
        
        // Verify DB
        $stmt = $this->pdo->query("SELECT * FROM posts WHERE id = {$post['id']}");
        $this->assertIsArray($stmt->fetch());
    }

    public function test_it_finds_post_by_id_and_slug(): void
    {
        $post = $this->repository->create([
            'user_id' => 1,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => 'Content'
        ]);

        $foundById = $this->repository->findById((int) $post['id']);
        $this->assertSame('hello-world', $foundById['slug']);

        $foundBySlug = $this->repository->findBySlug('hello-world');
        $this->assertSame((int) $post['id'], (int) $foundBySlug['id']);

        $this->assertNull($this->repository->findById(999));
        $this->assertNull($this->repository->findBySlug('not-exist'));
    }

    public function test_it_updates_a_post(): void
    {
        $post = $this->repository->create([
            'user_id' => 1,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'content' => 'Content'
        ]);

        $updated = $this->repository->update((int) $post['id'], [
            'title' => 'New Title',
            'status' => 'published'
        ]);

        $this->assertSame('New Title', $updated['title']);
        $this->assertSame('published', $updated['status']);
        $this->assertSame('original-title', $updated['slug']); // unchanged
    }

    public function test_it_deletes_a_post(): void
    {
        $post = $this->repository->create([
            'user_id' => 1,
            'title' => 'Title',
            'slug' => 'title',
            'content' => 'Content'
        ]);

        $this->assertTrue($this->repository->delete((int) $post['id']));
        $this->assertNull($this->repository->findById((int) $post['id']));
        
        // Delete again should return false
        $this->assertFalse($this->repository->delete((int) $post['id']));
    }

    public function test_it_paginates_and_filters_posts(): void
    {
        // Seed 5 posts
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create([
                'user_id' => 1,
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'content' => "Content of post {$i} with some keywords like APPLE",
                'status' => $i % 2 === 0 ? 'published' : 'draft', // 2 and 4 are published
                'created_at' => "2026-07-08 12:0{$i}:00" // ensure order
            ]);
        }

        // Test basic pagination
        $result = $this->repository->paginate(1, 3);
        $this->assertCount(3, $result['items']);
        $this->assertSame(5, $result['meta']['total']);
        $this->assertSame(2, $result['meta']['last_page']);
        
        // Test status filter
        $result = $this->repository->paginate(1, 10, ['status' => 'published']);
        $this->assertCount(2, $result['items']);
        
        // Test search filter
        $result = $this->repository->paginate(1, 10, ['search' => 'APPLE']);
        $this->assertCount(5, $result['items']);
        
        $result = $this->repository->paginate(1, 10, ['search' => 'post 3']);
        $this->assertCount(1, $result['items']);
    }
}
