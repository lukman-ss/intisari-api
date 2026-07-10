<?php

declare(strict_types=1);

/** @var \Lukman\Router\Router $router */

use App\Controllers\AuthController;
use App\Middleware\AuthTokenMiddleware;

use App\Middleware\RateLimitMiddleware;

$router->post('/api/auth/register', [AuthController::class, 'register'])
       ->middleware(new RateLimitMiddleware(3, 3600, 'auth_register'));

$router->post('/api/auth/login', [AuthController::class, 'login'])
       ->middleware(new RateLimitMiddleware(5, 60, 'auth_login'));

$router->get('/api/auth/me', [AuthController::class, 'me'])
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/auth/logout', [AuthController::class, 'logout'])
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/auth/refresh', [AuthController::class, 'refresh'])
       ->middleware(AuthTokenMiddleware::class);
