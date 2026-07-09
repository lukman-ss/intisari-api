<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\TokenService;
use App\Support\AuthManager;
use App\Support\ApiResponse;
use App\Repositories\UserRepository;

class AuthTokenMiddleware implements MiddlewareInterface
{
    public function __construct() {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $authHeader = $request->header('Authorization', '');
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return ApiResponse::error('Unauthenticated', 401, 'UNAUTHENTICATED');
        }

        $token = substr($authHeader, 7);
        $tokenService = app(\App\Support\TokenService::class);
        $validToken = $tokenService->findValidToken($token);

        if (!$validToken) {
            return ApiResponse::error('Unauthenticated', 401, 'UNAUTHENTICATED');
        }

        $userRepository = app(\App\Repositories\UserRepository::class);
        $user = $userRepository->findById((int) $validToken['user_id']);
        if (!$user || !(int) $user['is_active']) {
            return ApiResponse::error('Unauthenticated', 401, 'UNAUTHENTICATED');
        }

        $authManager = app(\App\Support\AuthManager::class);
        $authManager->setUser($user);
        $authManager->setToken($validToken);

        return $handler->handle($request);
    }
}
