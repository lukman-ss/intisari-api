<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

class TokenService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function hashToken(string $plain): string
    {
        $config = function_exists('config') ? config('auth', []) : [];
        $algo = $config['hash_algo'] ?? 'sha256';
        
        return hash($algo, $plain);
    }

    public function createToken(int $userId, string $name = 'default', array $abilities = ['*']): array
    {
        $plain = bin2hex(random_bytes(32)); // 64 chars
        $hash = $this->hashToken($plain);
        
        $config = function_exists('config') ? config('auth', []) : [];
        $ttlMinutes = (int) ($config['token_ttl_minutes'] ?? 1440);
        
        $expiresAt = null;
        if ($ttlMinutes > 0) {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO api_tokens (user_id, name, token_hash, abilities, expires_at)
            VALUES (:user_id, :name, :token_hash, :abilities, :expires_at)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => $hash,
            'abilities' => json_encode($abilities),
            'expires_at' => $expiresAt
        ]);

        $tokenId = (int) $this->pdo->lastInsertId();

        $fetchStmt = $this->pdo->prepare("SELECT * FROM api_tokens WHERE id = ?");
        $fetchStmt->execute([$tokenId]);
        $tokenData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        // Decode JSON for the array representation
        if (is_string($tokenData['abilities'])) {
            $tokenData['abilities'] = json_decode($tokenData['abilities'], true);
        }

        return [
            'plain_token' => $plain,
            'token' => $tokenData
        ];
    }

    public function findValidToken(string $plain): ?array
    {
        $hash = $this->hashToken($plain);
        
        $stmt = $this->pdo->prepare("SELECT * FROM api_tokens WHERE token_hash = ?");
        $stmt->execute([$hash]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            return null;
        }

        if ($token['expires_at'] !== null) {
            $expiresAt = strtotime($token['expires_at'] . ' UTC'); // Ensure UTC comparison
            if ($expiresAt < time()) {
                return null; // Token expired
            }
        }

        // Update last used at
        $updateStmt = $this->pdo->prepare("UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$token['id']]);

        // Merge updated last_used_at for return
        $token['last_used_at'] = gmdate('Y-m-d H:i:s');
        
        if (is_string($token['abilities'])) {
            $token['abilities'] = json_decode($token['abilities'], true);
        }
        
        return $token;
    }

    public function revokeToken(string $plain): bool
    {
        $hash = $this->hashToken($plain);
        $stmt = $this->pdo->prepare("DELETE FROM api_tokens WHERE token_hash = ?");
        $stmt->execute([$hash]);
        
        return $stmt->rowCount() > 0;
    }

    public function revokeAllForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM api_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->rowCount();
    }

    public function getUserTokens(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, abilities, last_used_at, expires_at, created_at FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tokens as &$token) {
            if (is_string($token['abilities'])) {
                $token['abilities'] = json_decode($token['abilities'], true);
            }
        }

        return $tokens;
    }

    public function revokeTokenById(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM api_tokens WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        return $stmt->rowCount() > 0;
    }
}
