<?php

declare(strict_types=1);

namespace App\Support;

class Logger
{
    private string $logFile;
    private array $sensitiveKeys = [
        'password', 
        'password_confirmation',
        'token', 
        'authorization', 
        'secret',
        'api_key'
    ];
    private static ?string $requestId = null;

    public static function setRequestId(?string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__, 2) . '/storage/logs/app.log';
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => $message,
        ];

        if (self::$requestId !== null) {
            $payload['request_id'] = self::$requestId;
        }

        if (!empty($context)) {
            $payload['context'] = $this->maskContext($context);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        
        @file_put_contents($this->logFile, $json, FILE_APPEND | LOCK_EX);
    }

    private function maskContext(array $context): array
    {
        $masked = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string) $key);
            
            $isSensitive = false;
            foreach ($this->sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $masked[$key] = '********';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskContext($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
