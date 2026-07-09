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
        $allowedOrigins = config('cors.allowed_origins', getenv('CORS_ALLOWED_ORIGINS') ?: '*');
        
        // Coba baca Origin dari Request jika didukung, jika tidak fallback ke $_SERVER
        $origin = '';
        if (method_exists($request, 'header')) {
            $origin = $request->header('Origin', '');
        }
        if (empty($origin)) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }

        if ($request->method() === 'OPTIONS') {
            $response = new Response('', 204);
            $this->addCorsHeaders($response, (string) $allowedOrigins, $origin);
            return $response;
        }

        $response = $handler->handle($request);
        $this->addCorsHeaders($response, (string) $allowedOrigins, $origin);
        
        return $response;
    }

    private function addCorsHeaders(Response $response, string $allowedOrigins, string $origin): void
    {
        $origins = array_map('trim', explode(',', $allowedOrigins));
        
        if (in_array('*', $origins) || in_array($origin, $origins)) {
            $response->header('Access-Control-Allow-Origin', in_array('*', $origins) ? '*' : $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
