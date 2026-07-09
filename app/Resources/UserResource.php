<?php

declare(strict_types=1);

namespace App\Resources;

use App\Support\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'name' => (string) $this->resource['name'],
            'email' => (string) $this->resource['email'],
            'is_active' => (bool) $this->resource['is_active'],
            'created_at' => (string) $this->resource['created_at'],
            'updated_at' => (string) $this->resource['updated_at'],
        ];
    }
}
