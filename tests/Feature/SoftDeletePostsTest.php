<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Lukman\Http\Request;
use App\Support\TokenService;
use App\Repositories\UserRepository;
use App\Repositories\PostRepository;

class SoftDeletePostsTest extends TestCase
{
    private array $user;
    private string $plainToken;
    private array $post;
    private PostRepository $postRepo;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $pdo = $this->pdo;
        $userRepo = new UserRepository($pdo);
        $this->postRepo = new PostRepository($pdo);
        $tokenService = new TokenService($pdo);
        
        $this->user = $userRepo->create([
            'name' => 'Author',
            'email' => 'author@example.com',
            'password_hash' => 'hash'
        ]);
        
        $tokenData = $tokenService->createToken((int) $this->user['id'], 'mobile', ['posts.read', 'posts.delete']);
        $this->plainToken = $tokenData['plain_token'];

        $this->post = $this->postRepo->create([
            'user_id' => $this->user['id'],
            'title' => 'My Post',
            'slug' => 'my-post',
            'content' => 'Content',
            'status' => 'published',
        ]);
    }

    public function test_it_soft_deletes_a_post(): void
    {
        $request = new Request('DELETE', '/api/posts/' . $this->post['id'], [], [], [
            'Authorization' => 'Bearer ' . $this->plainToken
        ]);
        
        $response = $this->app->handle($request);
        $this->assertSame(204, $response->status());

        // Verify in DB that it has deleted_at
        $stmt = $this->pdo->query("SELECT deleted_at FROM posts WHERE id = " . $this->post['id']);
        $deletedAt = $stmt->fetchColumn();
        
        $this->assertNotNull($deletedAt);
        
        // Verify findById returns null
        $this->assertNull($this->postRepo->findById((int) $this->post['id']));
    }

    public function test_soft_deleted_post_is_not_listed(): void
    {
        $this->postRepo->delete((int) $this->post['id']);
        
        $request = new Request('GET', '/api/posts', [], [], [
            'Authorization' => 'Bearer ' . $this->plainToken
        ]);
        
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->content(), true);
        
        $this->assertCount(0, $body['data']['items']);
    }

    public function test_force_delete_removes_from_database(): void
    {
        $this->postRepo->forceDelete((int) $this->post['id']);
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM posts WHERE id = " . $this->post['id']);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(0, $count);
    }
}
