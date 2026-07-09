<?php

declare(strict_types=1);

return [
    'allowed_origins' => getenv('CORS_ALLOWED_ORIGINS') ?: '*',
];
