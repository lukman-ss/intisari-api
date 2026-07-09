<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Exceptions\ForbiddenException;
use App\Support\AuthManager;

class AbilityMiddleware implements MiddlewareInterface
{
    public function __construct(private string $ability)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $authManager = app(AuthManager::class);

        if (!$authManager->can($this->ability)) {
            throw new ForbiddenException('Missing required ability: ' . $this->ability);
        }

        return $handler->handle($request);
    }
}
