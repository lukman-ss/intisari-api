<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Resources\UserResource;
use App\Resources\PostResource;

class ResourceTest extends TestCase
{
    public function test_user_resource_transforms_correctly_and_hides_password(): void
    {
        $userData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password_hash' => 'secret_hash',
            'is_active' => 1,
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
        ];

        $resource = UserResource::make($userData);

        $this->assertArrayNotHasKey('password_hash', $resource);
        $this->assertSame(1, $resource['id']);
        $this->assertSame('John Doe', $resource['name']);
        $this->assertSame('john@example.com', $resource['email']);
        $this->assertTrue($resource['is_active']);
    }

    public function test_post_resource_transforms_correctly(): void
    {
        $postData = [
            'id' => 10,
            'user_id' => 1,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => 'Content here',
            'status' => 'published',
            'published_at' => '2023-01-01 10:00:00',
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-01 10:00:00',
            'deleted_at' => null,
        ];

        $resource = PostResource::make($postData);

        $this->assertSame(10, $resource['id']);
        $this->assertSame(1, $resource['user_id']);
        $this->assertSame('published', $resource['status']);
        $this->assertSame('2023-01-01 10:00:00', $resource['published_at']);
        $this->assertArrayNotHasKey('deleted_at', $resource);
    }

    public function test_resource_collection(): void
    {
        $users = [
            [
                'id' => 1,
                'name' => 'User 1',
                'email' => 'u1@example.com',
                'is_active' => 1,
                'created_at' => '2023-01-01 10:00:00',
                'updated_at' => '2023-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'name' => 'User 2',
                'email' => 'u2@example.com',
                'is_active' => 0,
                'created_at' => '2023-01-01 10:00:00',
                'updated_at' => '2023-01-01 10:00:00',
            ],
        ];

        $collection = UserResource::collection($users);

        $this->assertCount(2, $collection);
        $this->assertSame(1, $collection[0]['id']);
        $this->assertSame(2, $collection[1]['id']);
        $this->assertTrue($collection[0]['is_active']);
        $this->assertFalse($collection[1]['is_active']);
    }
}
