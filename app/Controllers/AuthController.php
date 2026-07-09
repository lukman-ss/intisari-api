<?php

declare(strict_types=1);

namespace App\Controllers;

use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\ApiResponse;
use App\Support\RequestValidator;
use App\Support\PasswordHasher;
use App\Support\TokenService;
use App\Repositories\UserRepository;
use App\Exceptions\ApiValidationException;
use Lukman\Validation\MessageBag;
use App\Support\AuthManager;
use App\Resources\UserResource;

class AuthController extends Controller
{
    public function register(Request $request): Response
    {
        $validator = app(RequestValidator::class);
        $userRepository = app(UserRepository::class);
        $hasher = app(PasswordHasher::class);
        $tokenService = app(TokenService::class);

        $input = $this->input($request);

        $validated = $validator->validate($input, [
            'name' => ['required', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $email = strtolower($validated['email']);

        if ($userRepository->findByEmail($email)) {
            throw new ApiValidationException(['email' => ['The email has already been taken.']]);
        }

        $user = $userRepository->create([
            'name' => $validated['name'],
            'email' => $email,
            'password_hash' => $hasher->hash($validated['password']),
        ]);

        $tokenData = $tokenService->createToken((int) $user['id'], 'register');

        return $this->success([
            'user' => UserResource::make($user),
            'token' => $tokenData['plain_token']
        ], 'Registered', 201);
    }

    public function login(Request $request): Response
    {
        $validator = app(RequestValidator::class);
        $userRepository = app(UserRepository::class);
        $hasher = app(PasswordHasher::class);
        $tokenService = app(TokenService::class);

        $input = $this->input($request);

        $validated = $validator->validate($input, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($validated['email']);
        $user = $userRepository->findByEmail($email);

        if (!$user || !(int) $user['is_active'] || !$hasher->verify($validated['password'], $user['password_hash'])) {
            $logger = new \App\Support\Logger();
            $logger->warning('Failed login attempt', ['email' => $email]);
            return $this->error('Invalid credentials', 401);
        }

        $tokenData = $tokenService->createToken((int) $user['id'], 'login');

        return $this->success([
            'user' => UserResource::make($user),
            'token' => $tokenData['plain_token']
        ], 'Logged in', 200);
    }

    public function me(Request $request): Response
    {
        $authManager = app(\App\Support\AuthManager::class);
        $user = $this->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        return $this->success([
            'user' => UserResource::make($user)
        ], 'OK', 200);
    }

    public function logout(Request $request): Response
    {
        $tokenService = app(TokenService::class);
        $authHeader = $request->header('Authorization', '');
        
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenService->revokeToken($token);
        }

        return $this->success(null, 'Logged out', 200);
    }

    public function refresh(Request $request): Response
    {
        $user = $this->user();
        $authManager = app(AuthManager::class);
        $tokenService = app(TokenService::class);
        
        $oldTokenData = $authManager->token();
        $authHeader = $request->header('Authorization', '');
        $oldPlainToken = substr($authHeader, 7);
        
        // Revoke the old token
        $tokenService->revokeToken($oldPlainToken);
        
        // Create new token with same name and abilities
        $name = $oldTokenData['name'] ?? 'refresh';
        $abilities = $oldTokenData['abilities'] ?? ['*'];
        
        $newTokenData = $tokenService->createToken((int) $user['id'], $name, $abilities);
        
        return $this->success([
            'token' => $newTokenData['plain_token']
        ], 'Token refreshed', 200);
    }
}
