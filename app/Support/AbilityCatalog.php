<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ability Catalog
 * 
 * Central source of truth for all abilities (permissions) in the system.
 */
final class AbilityCatalog
{
    public const POSTS_READ = 'posts.read';
    public const POSTS_CREATE = 'posts.create';
    public const POSTS_UPDATE = 'posts.update';
    public const POSTS_DELETE = 'posts.delete';
    
    public const TOKENS_READ = 'tokens.read';
    public const TOKENS_CREATE = 'tokens.create';
    public const TOKENS_REVOKE = 'tokens.revoke';

    /**
     * Get all available abilities in the system.
     * 
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::POSTS_READ,
            self::POSTS_CREATE,
            self::POSTS_UPDATE,
            self::POSTS_DELETE,
            self::TOKENS_READ,
            self::TOKENS_CREATE,
            self::TOKENS_REVOKE,
        ];
    }
}
