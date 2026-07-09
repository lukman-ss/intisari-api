<?php

declare(strict_types=1);

use Intisari\Routing\Router;
use App\Controllers\AuthController;
use App\Middleware\AuthTokenMiddleware;

/** @var Router $router */

$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);

$router->get('/api/auth/me', [AuthController::class, 'me'])
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/auth/logout', [AuthController::class, 'logout'])
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/auth/refresh', [AuthController::class, 'refresh'])
       ->middleware(AuthTokenMiddleware::class);
