<?php

declare(strict_types=1);

/** @var \Lukman\Router\Router $router */

use App\Controllers\PostsController;
use App\Middleware\AuthTokenMiddleware;
use App\Middleware\AbilityMiddleware;

$router->get('/api/posts', [PostsController::class, 'index'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.read'));

$router->post('/api/posts', [PostsController::class, 'store'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.create'));

$router->get('/api/posts/{id}', [PostsController::class, 'show'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.read'));

$router->put('/api/posts/{id}', [PostsController::class, 'update'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.update'));

$router->patch('/api/posts/{id}', [PostsController::class, 'update'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.update'));

$router->delete('/api/posts/{id}', [PostsController::class, 'destroy'])
       ->middleware(AuthTokenMiddleware::class)
       ->middleware(new AbilityMiddleware('posts.delete'));
