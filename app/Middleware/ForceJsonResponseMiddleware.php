<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\Request;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Response;

class ForceJsonResponseMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (method_exists($request, 'headers')) {
            $request->headers()->set('Accept', 'application/json');
        }

        $response = $handler->handle($request);
        
        if (method_exists($response, 'header')) {
            $response->header('Content-Type', 'application/json');
        }
        
        return $response;
    }
}
