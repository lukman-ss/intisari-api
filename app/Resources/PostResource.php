<?php

declare(strict_types=1);

namespace App\Resources;

use App\Support\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'user_id' => (int) $this->resource['user_id'],
            'title' => (string) $this->resource['title'],
            'slug' => (string) $this->resource['slug'],
            'content' => (string) $this->resource['content'],
            'status' => (string) $this->resource['status'],
            'published_at' => !empty($this->resource['published_at']) ? (string) $this->resource['published_at'] : null,
            'created_at' => (string) $this->resource['created_at'],
            'updated_at' => (string) $this->resource['updated_at'],
        ];
    }
}
