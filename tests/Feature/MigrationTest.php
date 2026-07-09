<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use PDO;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

class MigrationTest extends TestCase
{
    private string $migrationPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Base TestCase already runs migrations. But here we test migration runner manually.
        // Wait, Base TestCase runs migrations using MigrationRunner on $this->pdo!
        // We shouldn't use the Base TestCase $this->pdo if we want to test empty DB.
        // Let's create a fresh PDO.
        
        $this->migrationPath = dirname(__DIR__, 2) . '/database/migrations';
    }

    public function test_it_runs_migrations(): void
    {
        // Use a fresh in-memory db for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $runner = new MigrationRunner($pdo);
        
        // This relies on whatever is in database/migrations
        $executed = $runner->run($this->migrationPath);
        
        // It might run multiple migrations depending on what's in the folder.
        // Let's just assert it executed something.
        $this->assertNotEmpty($executed);
        
        // Ensure table exists (we know users table is created)
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertSame('users', $stmt->fetchColumn());
        
        // Ensure running again does nothing
        $executedAgain = $runner->run($this->migrationPath);
        $this->assertEmpty($executedAgain);
    }
    
    public function test_migrate_fresh_command_executes_in_testing_mode(): void
    {
        putenv('APP_ENV=testing');
        
        $binary = dirname(__DIR__, 2) . '/intisari';
        
        $output = [];
        $exitCode = 0;
        
        // Execute without --force since we are in testing environment
        exec('php ' . escapeshellarg($binary) . ' migrate:fresh', $output, $exitCode);
        
        $outputStr = implode("\n", $output);
        
        $this->assertStringContainsString('Dropped all tables', $outputStr);
        $this->assertSame(0, $exitCode);
    }
}
