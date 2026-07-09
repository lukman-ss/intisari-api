<?php

declare(strict_types=1);

namespace App\Support;

use Lukman\Http\Response;
use stdClass;

class ApiResponse
{
    public static function success(array|object|null $data = null, string $message = 'OK', int $status = 200): Response
    {
        return self::build(true, $message, $data, null, $status);
    }

    public static function created(array|object|null $data = null, string $message = 'Created'): Response
    {
        return self::build(true, $message, $data, null, 201);
    }

    public static function noContent(): Response
    {
        return new Response('', 204);
    }

    public static function error(string $message, int $status = 400, string $code = 'INTERNAL_ERROR', array $errors = []): Response
    {
        return self::build(false, $message, null, $errors, $status, $code);
    }

    public static function validation(array $errors, string $message = 'Validation failed'): Response
    {
        return self::build(false, $message, null, $errors, 422, 'VALIDATION_ERROR');
    }

    public static function paginated(array $items, array $meta, string $message = 'OK'): Response
    {
        return self::build(true, $message, ['items' => $items, 'meta' => $meta], null, 200);
    }

    private static function build(bool $success, string $message, array|object|null $data, ?array $errors, int $status, ?string $code = null): Response
    {
        if ($success) {
            $payload = [
                'success' => true,
                'message' => $message,
                'data' => $data ?? new stdClass(),
            ];
        } else {
            $format = getenv('API_ERROR_FORMAT') ?: (function_exists('config') ? config('api.error_format', 'default') : 'default');
            
            if ($format === 'problem') {
                $payload = [
                    'type' => 'about:blank',
                    'title' => $code === 'VALIDATION_ERROR' ? 'Validation failed' : $message,
                    'status' => $status,
                    'detail' => $message,
                ];
                if (!empty($errors)) {
                    $payload['errors'] = $errors;
                } else {
                    $payload['errors'] = new stdClass();
                }
            } else {
                $payload = [
                    'success' => false,
                    'message' => $message,
                ];
                if ($code !== null) {
                    $payload['code'] = $code;
                }
                $payload['errors'] = empty($errors) ? new stdClass() : $errors;
            }
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($encoded ?: '', $status, ['Content-Type' => 'application/json']);
    }
}
