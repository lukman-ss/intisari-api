<?php

declare(strict_types=1);

return [
    'allowed_origins' => getenv('CORS_ALLOWED_ORIGINS') ?: '',
    'supports_credentials' => filter_var(getenv('CORS_SUPPORTS_CREDENTIALS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
