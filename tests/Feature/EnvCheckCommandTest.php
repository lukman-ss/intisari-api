<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class EnvCheckCommandTest extends TestCase
{
    public function test_env_check_command_outputs_valid(): void
    {
        $binary  = dirname(__DIR__, 2) . '/intisari';
        $dbPath  = dirname(__DIR__, 2) . '/database/api.sqlite';

        // Inject env vars explicitly so the test is self-contained in CI (no .env needed).
        // Use proc_open for cross-platform compatibility (KEY=val prefix fails on Windows).
        $env = array_merge($_ENV, [
            'APP_NAME'      => 'TestApp',
            'APP_ENV'       => 'testing',
            'APP_DEBUG'     => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE'   => $dbPath,
        ]);

        $process = proc_open(
            ['php', $binary, 'env:check'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($binary),
            $env
        );

        $this->assertIsResource($process, 'Failed to start intisari env:check process');

        $stdout   = stream_get_contents($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $outputStr = $stdout . $stderr;

        $this->assertStringContainsString('Validating environment variables...', $outputStr);
        $this->assertStringContainsString('[OK] APP_NAME', $outputStr);
        $this->assertStringContainsString('[OK] DB_CONNECTION', $outputStr);
        $this->assertStringContainsString('Environment is fully valid.', $outputStr);
        $this->assertSame(0, $exitCode);
    }
}
