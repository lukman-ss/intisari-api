<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Intisari\Application;
use Lukman\Http\Request;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Support\PasswordHasher;
use App\Support\TokenService;
use App\Repositories\PostRepository;
use PDO;

class PostsPaginationTest extends TestCase
{
    private TokenService $tokenService;
    private string $token;
    private PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        
        $hasher = new PasswordHasher();
        $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)")
             ->execute(['Demo User', 'demo@example.com', $hasher->hash('secret123'), 1]);
        
        $this->tokenService = $this->app->make(TokenService::class);
        $tokenData = $this->tokenService->createToken(1, 'test');
        $this->token = $tokenData['plain_token'];

        $this->postRepo = new PostRepository($this->pdo);

        // Seed 30 posts
        for ($i = 1; $i <= 30; $i++) {
            $this->postRepo->create([
                'user_id' => 1,
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'content' => "Content {$i}",
                'status' => 'published',
            ]);
        }
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function test_it_uses_default_pagination(): void
    {
        $request = new Request('GET', '/api/posts', [], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertCount(15, $body['data']['items']); // Default 15
        
        $meta = $body['data']['meta'];
        $this->assertSame(1, $meta['page']);
        $this->assertSame(15, $meta['per_page']);
        $this->assertSame(30, $meta['total']);
        $this->assertSame(2, $meta['last_page']);
        $this->assertTrue($meta['has_more']);
    }

    public function test_it_uses_custom_pagination(): void
    {
        $request = new Request('GET', '/api/posts', ['page' => '2', 'per_page' => '10'], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertCount(10, $body['data']['items']);
        
        $meta = $body['data']['meta'];
        $this->assertSame(2, $meta['page']);
        $this->assertSame(10, $meta['per_page']);
        $this->assertSame(30, $meta['total']);
        $this->assertSame(3, $meta['last_page']);
        $this->assertTrue($meta['has_more']); // Because there are 3 pages, page 2 has_more = true
    }

    public function test_it_caps_per_page_to_max(): void
    {
        $request = new Request('GET', '/api/posts', ['page' => '1', 'per_page' => '1000'], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertCount(30, $body['data']['items']); // We only have 30, but per_page maxes at 100
        
        $meta = $body['data']['meta'];
        $this->assertSame(1, $meta['page']);
        $this->assertSame(100, $meta['per_page']); // Capped at 100
        $this->assertSame(30, $meta['total']);
        $this->assertSame(1, $meta['last_page']);
        $this->assertFalse($meta['has_more']);
    }

    public function test_it_handles_invalid_pagination_values(): void
    {
        $request = new Request('GET', '/api/posts', ['page' => '-5', 'per_page' => '-10'], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $meta = $body['data']['meta'];
        $this->assertSame(1, $meta['page']); // -5 becomes 1
        $this->assertSame(1, $meta['per_page']); // -10 becomes 1
    }

    public function test_it_sorts_posts(): void
    {
        $request = new Request('GET', '/api/posts', ['sort' => 'title', 'direction' => 'asc'], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $items = $body['data']['items'];
        $this->assertNotEmpty($items);
        
        // title sorted ASC means Post 1, Post 10, Post 11...
        $this->assertSame('Post 1', $items[0]['title']);
        $this->assertSame('Post 10', $items[1]['title']);
    }
}
