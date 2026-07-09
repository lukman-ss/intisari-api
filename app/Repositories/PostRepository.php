<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PostRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function paginate(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        
        $offset = ($page - 1) * $perPage;
        
        $whereConditions = ["deleted_at IS NULL"];
        $params = [];
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search']) && $filters['search'] !== '') {
            $whereConditions[] = "(title LIKE ? OR content LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereSql = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);
        
        // Count total items
        $countSql = "SELECT COUNT(*) FROM posts $whereSql";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        
        // Fetch items
        $allowedSorts = ['id', 'title', 'created_at', 'updated_at'];
        $allowedDirections = ['asc', 'desc'];
        
        $sortColumn = 'created_at';
        if (isset($filters['sort']) && in_array($filters['sort'], $allowedSorts)) {
            $sortColumn = $filters['sort'];
        }
        
        $sortDirection = 'DESC';
        if (isset($filters['direction']) && in_array(strtolower($filters['direction']), $allowedDirections)) {
            $sortDirection = strtoupper($filters['direction']);
        }

        $sql = "SELECT * FROM posts $whereSql ORDER BY {$sortColumn} {$sortDirection} LIMIT ? OFFSET ?";
        
        $fetchParams = array_merge($params, [$perPage, $offset]);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($fetchParams);
        $items = $stmt->fetchAll();
        
        $lastPage = max((int) ceil($total / $perPage), 1);
        
        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ]
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        
        return $post ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE slug = ? AND deleted_at IS NULL");
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        
        return $post ?: null;
    }

    public function create(array $data): array
    {
        $data['created_at'] ??= date('Y-m-d H:i:s');
        $data['updated_at'] ??= date('Y-m-d H:i:s');
        $data['status'] ??= 'draft';

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, title, slug, content, status, created_at, updated_at) 
            VALUES (:user_id, :title, :slug, :content, :status, :created_at, :updated_at)
        ");
        
        $stmt->execute([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'],
            'status' => $data['status'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at']
        ]);
        
        $id = (int) $this->pdo->lastInsertId();
        
        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $post = $this->findById($id);
        if (!$post) {
            return null;
        }

        $data['updated_at'] ??= date('Y-m-d H:i:s');
        
        $updates = [];
        $params = [];
        
        $allowedFields = ['title', 'slug', 'content', 'status', 'updated_at'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $post;
        }
        
        $params['id'] = $id;
        $setSql = implode(', ', $updates);
        
        $stmt = $this->pdo->prepare("UPDATE posts SET {$setSql} WHERE id = :id");
        $stmt->execute($params);
        
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE posts SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }

    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }
}
