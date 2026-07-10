<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\AbilityCatalog;

class AbilityCatalogTest extends TestCase
{
    public function test_catalog_is_not_empty(): void
    {
        $abilities = AbilityCatalog::all();
        
        $this->assertNotEmpty($abilities);
    }

    public function test_catalog_has_unique_abilities(): void
    {
        $abilities = AbilityCatalog::all();
        $uniqueAbilities = array_unique($abilities);
        
        $this->assertCount(count($uniqueAbilities), $abilities, 'Ability catalog contains duplicate values');
    }

    public function test_catalog_does_not_contain_wildcard_or_admin(): void
    {
        $abilities = AbilityCatalog::all();
        
        foreach ($abilities as $ability) {
            $this->assertNotSame('*', $ability, 'Abilities should not contain global wildcard');
            $this->assertStringNotContainsString('admin', $ability, 'Abilities should not contain admin unless admin roles exist');
        }
    }
}
