<?php

declare(strict_types=1);

namespace App\Support;

use Lukman\Http\Request;

class RequestInput
{
    /**
     * Parse JSON body from request.
     * Returns 400 JSON if payload is invalid JSON.
     *
     * @return array<string, mixed>
     */
    public static function json(Request $request): array
    {
        $body = $request->body();

        if (empty($body)) {
            return [];
        }

        if (is_string($body)) {
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \App\Exceptions\InvalidJsonException();
            }

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($body) ? $body : [];
    }

    /**
     * Get query parameters safely.
     *
     * @return array<string, mixed>
     */
    public static function query(Request $request): array
    {
        $query = $request->query();

        return is_array($query) ? $query : [];
    }

    /**
     * Get all input (query + json body + form input).
     *
     * @return array<string, mixed>
     */
    public static function all(Request $request): array
    {
        $query = self::query($request);
        
        $input = $request->input();
        $input = is_array($input) ? $input : [];

        // If body is empty, we don't need to parse JSON.
        // If it's not empty, we try to parse it as JSON. But only if Content-Type is json to avoid 400 on form-data, 
        // OR if it's requested directly. Let's be safe and check json if body is set.
        $bodyArray = [];
        $contentType = $request->header('Content-Type', '');
        $isJson = is_array($contentType) 
            ? in_array('application/json', $contentType) 
            : str_contains(strtolower((string)$contentType), 'application/json');

        if ($isJson && !empty($request->body())) {
            $bodyArray = self::json($request);
        } elseif (!empty($request->body()) && is_string($request->body())) {
            // Attempt json decode safely without failing, if it's not strictly json content type
            $decoded = json_decode($request->body(), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $bodyArray = $decoded;
            }
        }

        return array_merge($query, $input, $bodyArray);
    }
}
