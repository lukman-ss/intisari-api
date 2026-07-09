<?php

declare(strict_types=1);

return [
    'token_ttl_minutes' => (int) (getenv('AUTH_TOKEN_TTL_MINUTES') ?: 1440),
    'hash_algo' => getenv('AUTH_TOKEN_HASH_ALGO') ?: 'sha256',
];
