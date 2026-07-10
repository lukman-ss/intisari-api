<?php

declare(strict_types=1);

namespace Tests\Feature;

class SecuritySmokeTest extends SecurityRegressionTestCase
{
    public function test_public_route_can_be_accessed_without_token(): void
    {
        $response = $this->jsonRequest('GET', '/api/health');
        
        $this->assertSame(200, $response->status());
        $body = json_decode((string) $response->content(), true);
        $this->assertSame('ok', $body['data']['status']);
    }

    public function test_protected_route_rejects_request_without_token(): void
    {
        $response = $this->jsonRequest('GET', '/api/posts');
        
        $this->assertSame(401, $response->status());
    }

    public function test_protected_route_accepts_valid_token(): void
    {
        $user = $this->createUser();
        $tokenData = $this->createToken((int) $user['id']);
        
        $response = $this->jsonRequest('GET', '/api/posts', [], $tokenData['plain_token']);
        
        $this->assertSame(200, $response->status());
    }

    public function test_revoked_token_is_rejected(): void
    {
        $user = $this->createUser();
        $tokenData = $this->createToken((int) $user['id']);
        
        // Revoke the token
        $this->tokenService->revokeToken($tokenData['plain_token']);
        
        $response = $this->jsonRequest('GET', '/api/posts', [], $tokenData['plain_token']);
        
        $this->assertSame(401, $response->status());
    }
}
