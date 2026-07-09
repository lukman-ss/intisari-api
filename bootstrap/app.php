<?php

declare(strict_types=1);

use Intisari\Application;

$app = new Application(dirname(__DIR__));
$app->setAsGlobal();

$app->singleton(\Intisari\ExceptionHandler::class, function () {
    return new \App\Support\Handler();
});

$app->singleton(\App\Support\AuthManager::class, function () {
    return new \App\Support\AuthManager();
});

$app->middleware([
    \App\Middleware\RequestIdMiddleware::class,
    \App\Middleware\CorsMiddleware::class,
    \App\Middleware\ForceJsonResponseMiddleware::class,
    \App\Middleware\SecurityHeadersMiddleware::class,
]);

if (is_file($app->basePath('.env'))) {
    $app->loadEnvironment($app->basePath('.env'));
}

if (is_dir($app->configPath())) {
    $app->loadConfiguration($app->configPath());
}

$app->loadRoutes($app->routesPath('api.php'));

return $app;
