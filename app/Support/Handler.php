<?php

declare(strict_types=1);

namespace App\Support;

use Intisari\ExceptionHandler;
use Lukman\Http\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render(Throwable $e): Response
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $isApi = str_starts_with($requestUri, '/api');

        if ($isApi) {
            return $this->renderJson($e);
        }

        return parent::render($e);
    }

    private function renderJson(Throwable $e): Response
    {
        $status = 500;
        $message = 'Internal server error';
        $code = 'INTERNAL_ERROR';

        if ($e instanceof \App\Exceptions\ApiException) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode(), $e->getErrorCode(), $e->getErrors());
        }

        if ($e instanceof \App\Exceptions\ApiValidationException) {
            return ApiResponse::validation($e->getErrors(), $e->getMessage());
        }

        if ($e instanceof \Lukman\Router\Exception\RouteNotFoundException) {
            $status = 404;
            $message = 'Endpoint not found';
            $code = 'NOT_FOUND';
        } elseif ($e instanceof \Lukman\Router\Exception\MethodNotAllowedException) {
            $status = 405;
            $message = 'Method not allowed';
            $code = 'METHOD_NOT_ALLOWED';
        } elseif ($e instanceof \Lukman\Validation\Exception\ValidationException) {
            $status = 422;
            $message = 'Validation failed';
            $code = 'VALIDATION_ERROR';
        }

        $errors = [];
        $env = getenv('APP_ENV') ?: 'production';
        $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        $isLocalDebug = $env === 'local' && $debug === true;

        if ($isLocalDebug) {
            $errors['debug'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        $requestId = \App\Support\Logger::getRequestId();
        if ($requestId) {
            $errors['request_id'] = $requestId;
        }

        if ($status === 500) {
            $logger = new \App\Support\Logger();
            $logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return ApiResponse::error($message, $status, $code, $errors);
    }
}
