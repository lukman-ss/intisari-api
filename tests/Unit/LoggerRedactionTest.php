<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\Logger;

class LoggerRedactionTest extends TestCase
{
    private string $logFile;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = dirname(__DIR__, 2) . '/storage/logs/test-redaction.log';
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        $this->logger = new Logger($this->logFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function test_it_redacts_flat_sensitive_keys(): void
    {
        $this->logger->info('Test flat', [
            'PASSWORD' => 'my-secret',
            'api_token' => 'token-123',
            'public_key' => 'pub-123'
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('"PASSWORD":"********"', $content);
        $this->assertStringContainsString('"api_token":"********"', $content);
        $this->assertStringContainsString('"public_key":"pub-123"', $content);
        $this->assertStringNotContainsString('my-secret', $content);
        $this->assertStringNotContainsString('token-123', $content);
    }

    public function test_it_redacts_nested_arrays(): void
    {
        $this->logger->info('Test nested', [
            'user' => [
                'name' => 'John',
                'password_confirmation' => 'secret123',
                'preferences' => [
                    'theme' => 'dark',
                    'Authorization' => 'Bearer token'
                ]
            ]
        ]);

        $content = file_get_contents($this->logFile);
        
        $this->assertStringContainsString('"name":"John"', $content);
        $this->assertStringContainsString('"password_confirmation":"********"', $content);
        $this->assertStringContainsString('"theme":"dark"', $content);
        $this->assertStringContainsString('"Authorization":"********"', $content);
        
        $this->assertStringNotContainsString('secret123', $content);
        $this->assertStringNotContainsString('Bearer token', $content);
    }

    public function test_it_includes_request_id(): void
    {
        Logger::setRequestId('req-999');
        $this->logger->info('Test req id');
        
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('"request_id":"req-999"', $content);
        
        Logger::setRequestId(null);
    }
}
