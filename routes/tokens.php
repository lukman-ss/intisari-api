<?php

declare(strict_types=1);

use Lukman\Router\Router;
use App\Controllers\TokenController;
use App\Middleware\AuthTokenMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\AbilityMiddleware;

/** @var Router $router */

$router->get('/api/tokens', [TokenController::class, 'index'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('tokens.read'));

$router->post('/api/tokens', [TokenController::class, 'store'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('tokens.create'));

$router->delete('/api/tokens/{id}', [TokenController::class, 'destroy'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('tokens.revoke'));
