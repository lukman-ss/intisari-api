<?php

declare(strict_types=1);

namespace App\Support;

class Slugger
{
    public static function slug(string $value): string
    {
        // Convert to lowercase
        $slug = strtolower($value);
        
        // Replace non-alphanumeric characters with dashes
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Trim dashes from start and end
        $slug = trim((string) $slug, '-');
        
        // Fallback if empty
        if ($slug === '') {
            return 'n-a';
        }
        
        return $slug;
    }
}
