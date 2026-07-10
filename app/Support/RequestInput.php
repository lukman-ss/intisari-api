<?php

declare(strict_types=1);

namespace App\Support;

use Lukman\Http\Request;

class RequestInput
{
    private static int $maxBodySize = 1048576; // 1MB
    private static int $maxJsonDepth = 128;

    public static function setMaxBodySize(int $size): void
    {
        self::$maxBodySize = $size;
    }

    /**
     * Parse JSON body from request.
     * Returns 400 JSON if payload is invalid JSON.
     *
     * @return array<string, mixed>
     */
    public static function json(Request $request): array
    {
        return self::parseJsonBody($request, true);
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

        $bodyArray = [];
        $contentType = $request->header('Content-Type', '');
        $isJson = is_array($contentType) 
            ? in_array('application/json', $contentType) 
            : str_contains(strtolower((string)$contentType), 'application/json');

        if ($isJson) {
            $bodyArray = self::parseJsonBody($request, true);
        } else {
            // Attempt to parse strictly without failing if it isn't json content-type
            try {
                $bodyArray = self::parseJsonBody($request, false);
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return array_merge($query, $input, $bodyArray);
    }

    private static function parseJsonBody(Request $request, bool $strict): array
    {
        $contentLengthHeader = $request->header('Content-Length', '0');
        $contentLength = (int) (is_array($contentLengthHeader) ? ($contentLengthHeader[0] ?? 0) : $contentLengthHeader);
        
        if ($contentLength > self::$maxBodySize) {
            throw new \App\Exceptions\ApiException('Payload Too Large', 413, 'PAYLOAD_TOO_LARGE');
        }

        $body = $request->body();

        if (empty($body)) {
            return [];
        }

        if (is_string($body)) {
            if (strlen($body) > self::$maxBodySize) {
                throw new \App\Exceptions\ApiException('Payload Too Large', 413, 'PAYLOAD_TOO_LARGE');
            }

            $decoded = json_decode($body, true, self::$maxJsonDepth);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($strict) {
                    throw new \App\Exceptions\InvalidJsonException();
                }
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($body) ? $body : [];
    }
}
