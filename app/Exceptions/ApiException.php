<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message = 'Internal server error',
        protected int $statusCode = 500,
        protected string $errorCode = 'INTERNAL_ERROR',
        protected array $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
