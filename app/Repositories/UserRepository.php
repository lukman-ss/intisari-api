<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (name, email, password_hash, is_active)
            VALUES (:name, :email, :password_hash, :is_active)
        ");
        
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'is_active' => $data['is_active'] ?? 1,
        ]);
        
        $id = (int) $this->pdo->lastInsertId();
        
        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $values = [];
        
        foreach (['name', 'email', 'password_hash', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $values[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return $this->findById($id);
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values['id'] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        
        // Get total
        $totalStmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        $total = (int) $totalStmt->fetchColumn();
        
        // Get data
        $stmt = $this->pdo->prepare("SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage)
            ]
        ];
    }
}
