<?php

declare(strict_types=1);

namespace App\Support;

class AuthManager
{
    private ?array $user = null;
    private ?array $token = null;

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function setToken(?array $token): void
    {
        $this->token = $token;
    }

    public function token(): ?array
    {
        return $this->token;
    }

    public function id(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function can(string $ability): bool
    {
        if (!$this->token || !isset($this->token['abilities'])) {
            return false;
        }

        $abilities = $this->token['abilities'];
        
        if (!is_array($abilities)) {
            return false;
        }

        if (in_array('*', $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }
}
