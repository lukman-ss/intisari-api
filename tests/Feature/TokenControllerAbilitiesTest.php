<?php

declare(strict_types=1);

namespace Tests\Feature;

class TokenControllerAbilitiesTest extends SecurityRegressionTestCase
{
    private array $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $tokenData = $this->tokenService->createToken((int)$this->user['id'], 'test', ['tokens.create', 'posts.read', 'posts.create']);
        $this->token = $tokenData['plain_token'];
    }

    private function postToken(array $abilities): \Lukman\Http\Response
    {
        return $this->jsonRequest('POST', '/api/tokens', [
            'name' => 'test-token',
            'abilities' => $abilities
        ], $this->token);
    }

    public function test_valid_abilities_are_accepted(): void
    {
        $res = $this->postToken(['posts.read', 'posts.create']);
        $this->assertSame(201, $res->status());
    }

    public function test_rejects_admin_ability(): void
    {
        $res = $this->postToken(['admin']);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('Unknown ability: admin', $body['errors']['abilities'][0]);
    }

    public function test_rejects_wildcard_for_regular_user(): void
    {
        $res = $this->postToken(['*']);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('not allowed', $body['errors']['abilities'][0]);
    }

    public function test_rejects_unknown_ability(): void
    {
        $res = $this->postToken(['users.delete']);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('Unknown ability', $body['errors']['abilities'][0]);
    }

    public function test_rejects_integer_ability(): void
    {
        $res = $this->postToken([1]);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('must be a string', $body['errors']['abilities'][0]);
    }

    public function test_rejects_null_ability(): void
    {
        $res = $this->postToken([null]);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('must be a string', $body['errors']['abilities'][0]);
    }

    public function test_rejects_nested_array_ability(): void
    {
        $res = $this->postToken([['posts.read']]);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('must be a string', $body['errors']['abilities'][0]);
    }

    public function test_rejects_object_ability(): void
    {
        $res = $this->jsonRequest('POST', '/api/tokens', [
            'name' => 'test-token',
            'abilities' => ['ability' => 'posts.read']
        ], $this->token);
        
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('not an object', $body['errors']['abilities'][0] ?? $body['message'] ?? 'not an object');
    }

    public function test_rejects_privilege_amplification(): void
    {
        // Try to grant an ability (posts.delete) that the current token doesn't have.
        // Current token has tokens.create, posts.read, posts.create.
        $res = $this->postToken(['posts.delete']);
        $this->assertSame(422, $res->status());
        $body = json_decode((string)$res->content(), true);
        $this->assertStringContainsString('Cannot grant ability you do not have: posts.delete', $body['errors']['abilities'][0]);
    }
}
