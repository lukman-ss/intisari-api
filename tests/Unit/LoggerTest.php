<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\Logger;

class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = tempnam(sys_get_temp_dir(), 'test_log');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function test_it_writes_json_lines_and_masks_sensitive_data(): void
    {
        $logger = new Logger($this->logFile);
        
        $context = [
            'user' => 'admin',
            'password' => 'secret123',
            'api_token' => 'token_abcd',
            'Authorization' => 'Bearer token_xyz',
            'nested' => [
                'my_secret_key' => 'very_secret'
            ]
        ];

        $logger->info('Test log', $context);

        $this->assertFileExists($this->logFile);
        
        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(1, $lines);
        
        $data = json_decode(array_values($lines)[0], true);
        
        $this->assertSame('INFO', $data['level']);
        $this->assertSame('Test log', $data['message']);
        
        // Assert unmasked
        $this->assertSame('admin', $data['context']['user']);
        
        // Assert masked
        $this->assertSame('********', $data['context']['password']);
        $this->assertSame('********', $data['context']['api_token']);
        $this->assertSame('********', $data['context']['Authorization']);
        $this->assertSame('********', $data['context']['nested']['my_secret_key']);
    }

    public function test_it_handles_levels_correctly(): void
    {
        $logger = new Logger($this->logFile);
        
        $logger->warning('Warning message');
        $logger->error('Error message');
        
        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", $content));
        
        $this->assertCount(2, $lines);
        
        $data1 = json_decode(array_values($lines)[0], true);
        $this->assertSame('WARNING', $data1['level']);
        $this->assertSame('Warning message', $data1['message']);
        
        $data2 = json_decode(array_values($lines)[1], true);
        $this->assertSame('ERROR', $data2['level']);
        $this->assertSame('Error message', $data2['message']);
    }
}
