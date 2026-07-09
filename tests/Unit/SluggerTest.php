<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\Slugger;

class SluggerTest extends TestCase
{
    public function test_it_lowercases_string(): void
    {
        $this->assertSame('hello-world', Slugger::slug('HELLO WORLD'));
        $this->assertSame('some-title', Slugger::slug('Some Title'));
    }

    public function test_it_replaces_non_alphanumeric_with_dash(): void
    {
        $this->assertSame('hello-world', Slugger::slug('hello@world!'));
        $this->assertSame('100-pure', Slugger::slug('100% pure!'));
        $this->assertSame('multiple-dashes', Slugger::slug('multiple---dashes'));
    }

    public function test_it_trims_dashes(): void
    {
        $this->assertSame('hello-world', Slugger::slug('-hello-world-'));
        $this->assertSame('hello-world', Slugger::slug('  hello world  '));
        $this->assertSame('hello-world', Slugger::slug('!?hello world!?'));
    }

    public function test_it_returns_fallback_for_empty_or_special_only_strings(): void
    {
        $this->assertSame('n-a', Slugger::slug(''));
        $this->assertSame('n-a', Slugger::slug('   '));
        $this->assertSame('n-a', Slugger::slug('!@#$%^&*()'));
        $this->assertSame('n-a', Slugger::slug('---'));
    }
}
