# Database

The API ships with a lightweight query builder and migration system, designed to work smoothly with PDO.

## Migrations

Migrations are stored in the `database/migrations/` directory.

To run all pending migrations:
```bash
php console.php migrate
```

To rollback the last batch of migrations:
```bash
php console.php migrate:rollback
```

## Creating a new Migration

Create a new file in `database/migrations/` following the naming convention: `YYYYMMDDHHMMSS_create_table_name.php`.

```php
<?php
return function (\PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS example (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
};
```

## Default Tables

By default, the following tables are provided:
- `users`: Core authentication table.
- `api_tokens`: Stores Bearer tokens and their abilities.
- `posts`: Standard content entity linked to `users`.
- `migrations`: Internal table tracking applied migrations.
