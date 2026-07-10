<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\Request;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $origin = '';
        if (method_exists($request, 'header')) {
            $origin = $request->header('Origin', '');
        }
        if (empty($origin)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }

        if ($origin === '') {
            if ($request->method() === 'OPTIONS') {
                return new Response('', 204);
            }
            return $handler->handle($request);
        }

        $allowedOriginsConfig = config('cors.allowed_origins', getenv('CORS_ALLOWED_ORIGINS') ?: '');
        $supportsCredentials = config('cors.supports_credentials', filter_var(getenv('CORS_SUPPORTS_CREDENTIALS') ?: 'false', FILTER_VALIDATE_BOOLEAN));
        
        $origins = array_filter(array_map('trim', explode(',', (string) $allowedOriginsConfig)));

        $isAllowed = false;
        $allowOriginHeader = '';
        
        if (in_array('*', $origins, true)) {
            $isAllowed = true;
            $allowOriginHeader = '*';
        } elseif (in_array($origin, $origins, true)) {
            $isAllowed = true;
            $allowOriginHeader = $origin;
        }

        if ($request->method() === 'OPTIONS') {
            $response = new Response('', 204);
            if ($isAllowed) {
                $this->addCorsHeaders($response, $allowOriginHeader, $supportsCredentials);
                $response->header('Access-Control-Max-Age', '86400');
            }
            return $response;
        }

        $response = $handler->handle($request);
        
        if ($isAllowed) {
            $this->addCorsHeaders($response, $allowOriginHeader, $supportsCredentials);
        }
        
        return $response;
    }

    private function addCorsHeaders(Response $response, string $allowOriginHeader, bool $supportsCredentials): void
    {
        $response->header('Access-Control-Allow-Origin', $allowOriginHeader);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        if ($allowOriginHeader !== '*') {
            $response->header('Vary', 'Origin');
        }
        
        if ($supportsCredentials && $allowOriginHeader !== '*') {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
