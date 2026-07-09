<?php

declare(strict_types=1);

use Intisari\Routing\Router;
use App\Controllers\TokenController;
use App\Middleware\AuthTokenMiddleware;
use App\Middleware\RateLimitMiddleware;

/** @var Router $router */

$router->get('/api/tokens', [TokenController::class, 'index'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/tokens', [TokenController::class, 'store'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class);

$router->delete('/api/tokens/{id}', [TokenController::class, 'destroy'])
       ->middleware(RateLimitMiddleware::class)
       ->middleware(AuthTokenMiddleware::class);
