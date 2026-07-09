<?php

declare(strict_types=1);

namespace App\Support;

abstract class JsonResource
{
    protected array $resource;

    public function __construct(array $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     */
    abstract public function toArray(): array;

    /**
     * Create a new resource instance and resolve to array.
     */
    public static function make(array $resource): array
    {
        return (new static($resource))->toArray();
    }

    /**
     * Transform a collection of resources into an array.
     */
    public static function collection(array $resources): array
    {
        return array_map(function ($resource) {
            return static::make($resource);
        }, $resources);
    }
}
