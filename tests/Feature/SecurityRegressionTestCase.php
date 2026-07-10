<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Repositories\UserRepository;
use App\Repositories\PostRepository;
use App\Support\TokenService;
use App\Support\PasswordHasher;
use App\Support\Slugger;
use Lukman\Http\Request;
use Lukman\Http\Response;

abstract class SecurityRegressionTestCase extends TestCase
{
    protected UserRepository $userRepo;
    protected PostRepository $postRepo;
    protected TokenService $tokenService;
    protected PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepo = new UserRepository($this->pdo);
        $this->postRepo = new PostRepository($this->pdo);
        $this->tokenService = new TokenService($this->pdo);
        $this->hasher = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        // For SQLite in-memory, the DB is dropped automatically when PDO connection is destroyed.
        // We ensure a clean state by dropping all tables if necessary, though setup recreates it.
        $this->pdo->exec("PRAGMA writable_schema = 1; DELETE FROM sqlite_master WHERE type IN ('table', 'index', 'trigger'); PRAGMA writable_schema = 0; VACUUM; PRAGMA INTEGRITY_CHECK;");
        parent::tearDown();
    }

    protected function createUser(array $attributes = []): array
    {
        static $userCounter = 1;
        $default = [
            'name' => "User {$userCounter}",
            'email' => "user{$userCounter}@example.com",
            'password_hash' => $this->hasher->hash('secret123'),
        ];
        $userCounter++;

        return $this->userRepo->create(array_merge($default, $attributes));
    }

    protected function createToken(int $userId, array $abilities = ['posts.read', 'posts.create', 'posts.update', 'posts.delete', 'tokens.read', 'tokens.create', 'tokens.delete']): array
    {
        return $this->tokenService->createToken($userId, 'test-token', $abilities);
    }

    protected function createPublishedPost(int $userId, array $attributes = []): array
    {
        static $postCounter = 1;
        $title = $attributes['title'] ?? "Published Post {$postCounter}";
        $default = [
            'user_id' => $userId,
            'title' => $title,
            'slug' => Slugger::slug($title) . '-' . uniqid(),
            'content' => "Content for published post {$postCounter}",
            'status' => 'published',
        ];
        $postCounter++;

        return $this->postRepo->create(array_merge($default, $attributes));
    }

    protected function createDraftPost(int $userId, array $attributes = []): array
    {
        static $draftCounter = 1;
        $title = $attributes['title'] ?? "Draft Post {$draftCounter}";
        $default = [
            'user_id' => $userId,
            'title' => $title,
            'slug' => Slugger::slug($title) . '-' . uniqid(),
            'content' => "Content for draft post {$draftCounter}",
            'status' => 'draft',
        ];
        $draftCounter++;

        return $this->postRepo->create(array_merge($default, $attributes));
    }

    protected function jsonRequest(string $method, string $uri, array $payload = [], ?string $token = null): Response
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $body = !empty($payload) ? json_encode($payload) : '';
        $query = [];

        if ($method === 'GET' && !empty($payload)) {
            $query = $payload;
            $body = '';
        }

        $request = new Request($method, $uri, $query, [], $headers, $body);

        return $this->app->handle($request);
    }
}
