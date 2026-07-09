<?php

declare(strict_types=1);

namespace App\Support;

class PasswordHasher
{
    /**
     * Hash a plain text password.
     *
     * @param string $plain
     * @return string
     */
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /**
     * Verify a plain text password against a hash.
     *
     * @param string $plain
     * @param string $hash
     * @return bool
     */
    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
