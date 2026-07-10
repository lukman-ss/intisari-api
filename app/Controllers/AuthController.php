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
            $logger = new \App\Support\Logger();
            $logger->warning('Registration failed: email exists', ['email' => $email]);
            return $this->error('Registration could not be completed.', 400);
        }

        try {
            $user = $userRepository->create([
                'name' => $validated['name'],
                'email' => $email,
                'password_hash' => $hasher->hash($validated['password']),
            ]);
        } catch (\PDOException $e) {
            // Handle unique constraint violations gracefully
            if ((string)$e->getCode() === '23000' || $e->getCode() == 1062 || $e->getCode() == 19) {
                $logger = new \App\Support\Logger();
                $logger->warning('Registration failed: unique constraint', ['email' => $email]);
                return $this->error('Registration could not be completed.', 400);
            }
            throw $e;
        }

        $abilities = \App\Support\AbilityCatalog::all();
        $tokenData = $tokenService->createToken((int) $user['id'], 'register', $abilities);

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
        
        $verified = false;
        if ($user) {
            $verified = $hasher->verify($validated['password'], (string) $user['password_hash']);
        } else {
            // Mitigate timing attacks by verifying a dummy hash
            // This ensures login always takes roughly the same time even if email is not found
            $hasher->verify($validated['password'], '$2y$10$abcdefghijklmnopqrstuv');
        }

        if (!$user || !(int) $user['is_active'] || !$verified) {
            $logger = new \App\Support\Logger();
            $reason = !$user ? 'email not found' : (!(int) $user['is_active'] ? 'user inactive' : 'wrong password');
            $logger->warning("Failed login attempt: {$reason}", ['email' => $email]);
            return $this->error('Invalid credentials', 401);
        }

        $abilities = \App\Support\AbilityCatalog::all();
        $tokenData = $tokenService->createToken((int) $user['id'], 'login', $abilities);

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
        
        // Revoke the old token (returns false if already revoked, preventing race conditions)
        $revoked = $tokenService->revokeToken($oldPlainToken);
        if (!$revoked) {
            return $this->error('Token already revoked', 401);
        }
        
        $name = $oldTokenData['name'] ?? 'refresh';
        $oldAbilities = $oldTokenData['abilities'] ?? [];
        $abilities = $oldAbilities;
        
        $input = $this->input($request);
        if (isset($input['abilities'])) {
            $requestedAbilities = $input['abilities'];
            if (!is_array($requestedAbilities) || !array_is_list($requestedAbilities)) {
                throw new ApiValidationException(['abilities' => ['The abilities field must be a valid array.']]);
            }
            
            $abilities = [];
            foreach ($requestedAbilities as $index => $ability) {
                if (!is_string($ability)) {
                    throw new ApiValidationException(['abilities' => ["Ability at index {$index} must be a string."]]);
                }
                if (!in_array($ability, $oldAbilities, true)) {
                    throw new ApiValidationException(['abilities' => ["Cannot request ability you do not have: {$ability}"]]);
                }
                $abilities[] = $ability;
            }
            $abilities = array_values(array_unique($abilities));
        }
        
        $newTokenData = $tokenService->createToken((int) $user['id'], $name, $abilities);
        
        return $this->success([
            'token' => $newTokenData['plain_token']
        ], 'Token refreshed', 200);
    }
}
