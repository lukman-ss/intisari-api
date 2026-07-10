<?php

declare(strict_types=1);

namespace Tests\Feature;

class GenericLoginFailureTest extends SecurityRegressionTestCase
{
    private array $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser([
            'email' => 'valid@example.com',
            'password_hash' => password_hash('correct123', PASSWORD_BCRYPT)
        ]);
    }

    public function test_generic_failure_message_is_consistent(): void
    {
        // Case 1: Wrong password
        $resWrongPassword = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'valid@example.com',
            'password' => 'wrongpassword'
        ]);

        $this->assertSame(401, $resWrongPassword->status());
        $body1 = json_decode((string) $resWrongPassword->content(), true);
        $this->assertSame('Invalid credentials', $body1['message']);

        // Case 2: Email not found
        $resNotFound = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword'
        ]);

        $this->assertSame(401, $resNotFound->status());
        $body2 = json_decode((string) $resNotFound->content(), true);
        $this->assertSame('Invalid credentials', $body2['message']);
        
        // Ensure responses are completely identical
        $this->assertSame($body1, $body2, 'API should not leak user existence');
    }
}
