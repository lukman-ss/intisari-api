<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

class MigrationRunner
{
    public function __construct(private PDO $pdo)
    {
    }

    public function run(string $path): array
    {
        $this->createMigrationsTable();

        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return [];
        }

        sort($files);
        $executed = [];

        foreach ($files as $file) {
            $migrationName = basename($file, '.php');

            if ($this->hasRun($migrationName)) {
                continue;
            }

            $closure = require $file; // @security-ignore: dynamic require is expected here for migration files
            if (!is_callable($closure)) {
                throw new RuntimeException("Migration {$migrationName} must return a callable.");
            }

            $closure($this->pdo);

            $this->logMigration($migrationName);
            $executed[] = $migrationName;
        }

        return $executed;
    }

    public function fresh(string $path): array
    {
        // Query to get all tables in SQLite
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Disable foreign keys temporarily
        $this->pdo->exec('PRAGMA foreign_keys = OFF;');

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
        }

        // Re-enable foreign keys
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        return $this->run($path);
    }

    private function createMigrationsTable(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        SQL;
        
        $this->pdo->exec($sql);
    }

    private function hasRun(string $migration): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function logMigration(string $migration): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$migration]);
    }
}
