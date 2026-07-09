<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\PasswordHasher;

class PasswordHasherTest extends TestCase
{
    public function test_hash_creates_valid_password_hash(): void
    {
        $hasher = new PasswordHasher();
        
        $plain = 'secret123';
        $hash = $hasher->hash($plain);
        
        $this->assertNotEmpty($hash);
        $this->assertNotSame($plain, $hash);
        
        // Ensure that the output is indeed a valid password hash created natively by PHP
        $info = password_get_info($hash);
        $this->assertNotSame(0, $info['algo']);
        $this->assertNotEmpty($info['algoName']);
    }

    public function test_verify_checks_hash_correctly(): void
    {
        $hasher = new PasswordHasher();
        
        $plain = 'secure_password_!@#';
        $hash = $hasher->hash($plain);
        
        $this->assertTrue($hasher->verify($plain, $hash), 'Should verify correctly with right password');
        $this->assertFalse($hasher->verify('Secure_password_!@#', $hash), 'Should fail with wrong case');
        $this->assertFalse($hasher->verify('wrong_password', $hash), 'Should fail with wrong password');
    }
}
