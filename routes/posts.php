<?php

declare(strict_types=1);

use Intisari\Routing\Router;
use App\Controllers\PostsController;
use App\Middleware\AuthTokenMiddleware;

/** @var Router $router */

$router->get('/api/posts', [PostsController::class, 'index'])
       ->middleware(AuthTokenMiddleware::class);

$router->post('/api/posts', [PostsController::class, 'store'])
       ->middleware(AuthTokenMiddleware::class);

$router->get('/api/posts/{id}', [PostsController::class, 'show'])
       ->middleware(AuthTokenMiddleware::class);

$router->put('/api/posts/{id}', [PostsController::class, 'update'])
       ->middleware(AuthTokenMiddleware::class);

$router->patch('/api/posts/{id}', [PostsController::class, 'update'])
       ->middleware(AuthTokenMiddleware::class);

$router->delete('/api/posts/{id}', [PostsController::class, 'destroy'])
       ->middleware(AuthTokenMiddleware::class);
