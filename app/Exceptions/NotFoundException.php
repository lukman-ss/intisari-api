<?php

declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Resource not found', array $errors = [])
    {
        parent::__construct($message, 404, 'NOT_FOUND', $errors);
    }
}
