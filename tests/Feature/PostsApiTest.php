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

class PostsApiTest extends TestCase
{
    private TokenService $tokenService;
    private string $token;
    private string $otherToken;
    private PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed user
        $hasher = new PasswordHasher();
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Demo User', 'demo@example.com', $hasher->hash('secret123'), 1]);
        
        $this->tokenService = $this->app->make(TokenService::class);
        $tokenData = $this->tokenService->createToken(1, 'test', ['posts.read', 'posts.create', 'posts.update', 'posts.delete']);
        $this->token = $tokenData['plain_token'];

        $stmt->execute(['Other User', 'other@example.com', $hasher->hash('secret123'), 1]);
        $otherTokenData = $this->tokenService->createToken(2, 'test2', ['posts.read', 'posts.create', 'posts.update', 'posts.delete']);
        $this->otherToken = $otherTokenData['plain_token'];

        $this->postRepo = new PostRepository($this->pdo);
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function test_it_lists_posts(): void
    {
        // Create a test post
        $this->postRepo->create([
            'user_id' => 1,
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'Content here',
            'status' => 'published'
        ]);

        $request = new Request('GET', '/api/posts', [], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']['items']);
        $this->assertSame('First Post', $body['data']['items'][0]['title']);
    }

    public function test_it_creates_post_with_valid_data(): void
    {
        $payload = json_encode([
            'title' => 'New API Post',
            'content' => 'Testing API creation',
            'status' => 'draft'
        ]);

        $request = new Request('POST', '/api/posts', [], [], $this->getHeaders(), $payload);
        $response = $this->app->handle($request);
        
        $this->assertSame(201, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('New API Post', $body['data']['post']['title']);
        $this->assertSame('new-api-post', $body['data']['post']['slug']);
        $this->assertSame('Testing API creation', $body['data']['post']['content']);
        $this->assertSame('draft', $body['data']['post']['status']);
    }

    public function test_it_validates_post_creation(): void
    {
        $payload = json_encode([
            'title' => '', // missing title
            'content' => '' // missing content
        ]);

        $request = new Request('POST', '/api/posts', [], [], $this->getHeaders(), $payload);
        $response = $this->app->handle($request);
        
        $this->assertSame(422, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('title', $body['errors']);
        $this->assertArrayHasKey('content', $body['errors']);
    }

    public function test_it_shows_a_single_post(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'Single Post',
            'slug' => 'single-post',
            'content' => 'Content here'
        ]);

        $request = new Request('GET', '/api/posts/' . $post['id'], [], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertTrue($body['success']);
        $this->assertSame('Single Post', $body['data']['post']['title']);
    }

    public function test_it_returns_404_for_non_existent_post(): void
    {
        $request = new Request('GET', '/api/posts/999', [], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(404, $response->status());
    }

    public function test_it_updates_a_post_via_put(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'content' => 'Old Content'
        ]);

        $payload = json_encode([
            'title' => 'Updated Title',
            'content' => 'Updated Content',
            'status' => 'published'
        ]);

        $request = new Request('PUT', '/api/posts/' . $post['id'], [], [], $this->getHeaders(), $payload);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertSame('Updated Title', $body['data']['post']['title']);
        $this->assertSame('published', $body['data']['post']['status']);
    }

    public function test_it_partially_updates_a_post_via_patch(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'content' => 'Old Content'
        ]);

        $payload = json_encode([
            'status' => 'published' // only updating status
        ]);

        $request = new Request('PATCH', '/api/posts/' . $post['id'], [], [], $this->getHeaders(), $payload);
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        
        $this->assertSame('Old Title', $body['data']['post']['title']); // unchanged
        $this->assertSame('published', $body['data']['post']['status']); // changed
    }

    public function test_it_deletes_a_post(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'To be deleted',
            'slug' => 'to-be-deleted',
            'content' => 'Content'
        ]);

        $request = new Request('DELETE', '/api/posts/' . $post['id'], [], [], $this->getHeaders(), '');
        $response = $this->app->handle($request);
        
        $this->assertSame(204, $response->status());
        $this->assertSame('', (string) $response->content());

        // Verify it's gone
        $checkRequest = new Request('GET', '/api/posts/' . $post['id'], [], [], $this->getHeaders(), '');
        $checkResponse = $this->app->handle($checkRequest);
        $this->assertSame(404, $checkResponse->status());
    }

    public function test_all_routes_require_authentication(): void
    {
        $methods = [
            ['GET', '/api/posts'],
            ['POST', '/api/posts'],
            ['GET', '/api/posts/1'],
            ['PUT', '/api/posts/1'],
            ['PATCH', '/api/posts/1'],
            ['DELETE', '/api/posts/1'],
        ];

        foreach ($methods as [$method, $uri]) {
            $request = new Request($method, $uri, [], [], [], ''); // No headers
            $response = $this->app->handle($request);
            
            $this->assertSame(401, $response->status(), "Route {$method} {$uri} is not protected.");
        }
    }

    public function test_non_owner_cannot_update_post(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'My Post',
            'slug' => 'my-post',
            'content' => 'Content here',
        ]);

        $payload = json_encode(['title' => 'Hacked Title', 'content' => 'Hacked']);
        
        $headers = [
            'Authorization' => "Bearer {$this->otherToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $request = new Request('PUT', '/api/posts/' . $post['id'], [], [], $headers, $payload);
        $response = $this->app->handle($request);
        
        $this->assertSame(404, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertSame('NOT_FOUND', $body['code']);
    }

    public function test_draft_inaccessible_by_non_owner(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'content' => 'Content here',
            'status' => 'draft'
        ]);

        $headers = [
            'Authorization' => "Bearer {$this->otherToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $request = new Request('GET', '/api/posts/' . $post['id'], [], [], $headers, '');
        $response = $this->app->handle($request);
        
        $this->assertSame(404, $response->status());
    }

    public function test_published_accessible_by_non_owner(): void
    {
        $post = $this->postRepo->create([
            'user_id' => 1,
            'title' => 'Published Post',
            'slug' => 'published-post',
            'content' => 'Content here',
            'status' => 'published'
        ]);

        $headers = [
            'Authorization' => "Bearer {$this->otherToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $request = new Request('GET', '/api/posts/' . $post['id'], [], [], $headers, '');
        $response = $this->app->handle($request);
        
        $this->assertSame(200, $response->status());
    }
}
