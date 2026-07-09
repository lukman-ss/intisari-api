<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Database\MigrationRunner;
use PDO;
use PDOException;

class PostsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Ensure foreign keys are enabled in SQLite
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        
        $runner = new MigrationRunner($this->pdo);
        $runner->run(dirname(__DIR__, 2) . '/database/migrations');
    }

    public function test_posts_table_has_expected_columns(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info('posts')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        
        $expectedColumns = [
            'id', 'user_id', 'title', 'slug', 'content', 'status', 'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains($expectedColumn, $columnNames);
        }
    }

    public function test_posts_table_enforces_status_constraint(): void
    {
        // Insert a valid user first to satisfy foreign key constraint
        $this->pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Test', 'test@example.com', 'hash')");
        $userId = (int) $this->pdo->lastInsertId();

        // Valid status
        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (?, 'Title', 'slug-1', 'Content', 'draft', '2026-07-08 12:00:00', '2026-07-08 12:00:00')
        ");
        $this->assertTrue($stmt->execute([$userId]));

        // Invalid status
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('CHECK constraint failed');

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (?, 'Title 2', 'slug-2', 'Content', 'invalid_status', '2026-07-08 12:00:00', '2026-07-08 12:00:00')
        ");
        $stmt->execute([$userId]);
    }

    public function test_posts_table_enforces_slug_uniqueness(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Test', 'test@example.com', 'hash')");
        $userId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (?, 'Title', 'unique-slug', 'Content', 'published', '2026-07-08 12:00:00', '2026-07-08 12:00:00')
        ");
        $stmt->execute([$userId]);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: posts.slug');

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (?, 'Title 2', 'unique-slug', 'Content 2', 'draft', '2026-07-08 12:00:00', '2026-07-08 12:00:00')
        ");
        $stmt->execute([$userId]);
    }

    public function test_posts_table_has_foreign_key_constraint(): void
    {
        // Try inserting a post for a non-existent user
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (?, 'Title', 'slug', 'Content', 'draft', '2026-07-08 12:00:00', '2026-07-08 12:00:00')
        ");
        
        // user_id 9999 doesn't exist
        $stmt->execute([9999]);
    }
}
