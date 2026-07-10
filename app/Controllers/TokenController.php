<?php

declare(strict_types=1);

namespace App\Controllers;

use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\TokenService;
use App\Support\RequestValidator;

class TokenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user();
        $tokenService = app(TokenService::class);
        
        $tokens = $tokenService->getUserTokens((int) $user['id']);
        $tokens = array_map(function ($token) {
            unset($token['token_hash']);
            return $token;
        }, $tokens);
        
        return $this->success([
            'tokens' => $tokens
        ], 'Tokens retrieved', 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $validator = app(RequestValidator::class);
        $tokenService = app(TokenService::class);
        
        $input = $this->input($request);
        
        $validated = $validator->validate($input, [
            'name' => ['required', 'string', 'max:255'],
        ]);
        
        if (!isset($input['abilities'])) {
            throw new \App\Exceptions\ApiValidationException(['abilities' => ['The abilities field is required.']]);
        }
        
        $abilitiesInput = $input['abilities'];
        if (!is_array($abilitiesInput) || !array_is_list($abilitiesInput)) {
            throw new \App\Exceptions\ApiValidationException(['abilities' => ['The abilities field must be a valid array (not an object).']]);
        }
        
        if (count($abilitiesInput) > 50) {
            throw new \App\Exceptions\ApiValidationException(['abilities' => ['Too many abilities requested.']]);
        }

        $abilities = [];
        $catalog = \App\Support\AbilityCatalog::all();

        foreach ($abilitiesInput as $index => $ability) {
            if (!is_string($ability)) {
                throw new \App\Exceptions\ApiValidationException(['abilities' => ["Ability at index {$index} must be a string."]]);
            }
            if ($ability === '*') {
                throw new \App\Exceptions\ApiValidationException(['abilities' => ["The '*' ability is not allowed for regular users."]]);
            }
            if (!in_array($ability, $catalog, true)) {
                throw new \App\Exceptions\ApiValidationException(['abilities' => ["Unknown ability: {$ability}"]]);
            }
            $authManager = app(\App\Support\AuthManager::class);
            if (!$authManager->can($ability)) {
                throw new \App\Exceptions\ApiValidationException(['abilities' => ["Cannot grant ability you do not have: {$ability}"]]);
            }
            $abilities[] = $ability;
        }
        
        $abilities = array_values(array_unique($abilities));
        
        $tokenData = $tokenService->createToken((int) $user['id'], $validated['name'], $abilities);
        
        // Remove token_hash from response
        unset($tokenData['token']['token_hash']);
        
        return $this->success($tokenData, 'Token created', 201);
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = $this->user();
        $tokenService = app(TokenService::class);
        
        $tokenId = (int) $id;
        
        $deleted = $tokenService->revokeTokenById($tokenId, (int) $user['id']);
        
        if (!$deleted) {
            return $this->error('Token not found', 404, 'NOT_FOUND');
        }
        
        return $this->success(null, 'Token revoked', 200);
    }
}
