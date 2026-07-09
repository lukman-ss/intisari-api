<?php

declare(strict_types=1);

return function (\PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT DEFAULT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            abilities TEXT DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_user_id ON api_tokens(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_token_hash ON api_tokens(token_hash)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_expires_at ON api_tokens(expires_at)");
};
