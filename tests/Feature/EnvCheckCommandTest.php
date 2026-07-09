<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class EnvCheckCommandTest extends TestCase
{
    public function test_env_check_command_outputs_valid(): void
    {
        // Jalankan script CLI dan tangkap outputnya
        $binary = dirname(__DIR__, 2) . '/intisari';
        
        // Kita perlu pastikan script intisari executable atau panggil via php
        $output = [];
        $exitCode = 0;
        
        // Use PHP to execute to avoid execution permission issues on Windows
        exec('php ' . escapeshellarg($binary) . ' env:check', $output, $exitCode);
        
        $outputStr = implode("\n", $output);
        
        $this->assertStringContainsString('Validating environment variables...', $outputStr);
        $this->assertStringContainsString('[OK] APP_NAME', $outputStr);
        $this->assertStringContainsString('[OK] DB_CONNECTION', $outputStr);
        $this->assertStringContainsString('Environment is fully valid.', $outputStr);
        
        // Ensure exit code is 0
        $this->assertSame(0, $exitCode);
    }
}
