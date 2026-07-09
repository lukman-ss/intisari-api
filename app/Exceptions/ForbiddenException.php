<?php

declare(strict_types=1);

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Forbidden', array $errors = [])
    {
        parent::__construct($message, 403, 'FORBIDDEN', $errors);
    }
}
