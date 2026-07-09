<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\AuthManager;

class AuthManagerTest extends TestCase
{
    public function test_it_can_set_and_get_user(): void
    {
        $manager = new AuthManager();
        $this->assertNull($manager->user());
        $this->assertFalse($manager->check());
        $this->assertNull($manager->id());

        $user = ['id' => 1, 'name' => 'John'];
        $manager->setUser($user);

        $this->assertSame($user, $manager->user());
        $this->assertTrue($manager->check());
        $this->assertSame(1, $manager->id());

        $manager->setUser(null);
        $this->assertNull($manager->user());
        $this->assertFalse($manager->check());
        $this->assertNull($manager->id());
    }

    public function test_it_can_set_and_get_token(): void
    {
        $manager = new AuthManager();
        $this->assertNull($manager->token());

        $token = ['id' => 1, 'token_hash' => 'hash'];
        $manager->setToken($token);

        $this->assertSame($token, $manager->token());

        $manager->setToken(null);
        $this->assertNull($manager->token());
    }
}
