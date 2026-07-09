<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidJsonException extends ApiException
{
    public function __construct(string $message = 'Invalid JSON payload', array $errors = [])
    {
        parent::__construct($message, 400, 'INVALID_JSON', $errors);
    }
}
