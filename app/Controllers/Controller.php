<?php

declare(strict_types=1);

namespace App\Controllers;

use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\ApiResponse;
use App\Support\AuthManager;

abstract class Controller
{
    /**
     * Return a success JSON response.
     */
    protected function success(array|object|null $data = null, string $message = 'OK', int $status = 200): Response
    {
        return ApiResponse::success($data, $message, $status);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $status = 400, string $code = 'INTERNAL_ERROR', array $errors = []): Response
    {
        return ApiResponse::error($message, $status, $code, $errors);
    }

    /**
     * Parse and return JSON body from the request.
     */
    protected function input(Request $request): array
    {
        return \App\Support\RequestInput::json($request);
    }

    /**
     * Return query parameters from the request.
     */
    protected function query(Request $request): array
    {
        $query = $request->query();
        return is_array($query) ? $query : [];
    }

    /**
     * Retrieve the currently authenticated user.
     */
    protected function user(): ?array
    {
        return app(AuthManager::class)->user();
    }
}
