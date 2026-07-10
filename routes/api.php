<?php

declare(strict_types=1);

/** @var \Lukman\Router\Router $router */
use App\Support\ApiResponse;

$router->get('/', function () {
    return response()->json([
        'name' => 'Intisari API Starter',
        'status' => 'success',
        'message' => 'Welcome to the Intisari RESTful API Starter'
    ]);
});

$router->get('/api/health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'app' => getenv('APP_NAME') ?: config('app.name', 'Intisari API'),
        'environment' => getenv('APP_ENV') ?: config('app.env', 'production'),
        'timestamp' => gmdate('c'), // ISO-8601
    ]);
});

// Include modular route files
require __DIR__ . '/auth.php';
require __DIR__ . '/tokens.php';
require __DIR__ . '/posts.php';