<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\Logger;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $requestId = $request->header('X-Request-Id', '');
        
        if (empty($requestId) || !preg_match('/^[a-zA-Z0-9\-]{16,36}$/', $requestId)) {
            $requestId = bin2hex(random_bytes(16));
        }

        Logger::setRequestId($requestId);

        $response = $handler->handle($request);

        $response->header('X-Request-Id', $requestId);

        return $response;
    }
}
