<?php

declare(strict_types=1);

namespace App\Middleware;

use Lukman\Http\MiddlewareInterface;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\ApiResponse;

/**
 * Rate Limiter Minimal.
 * 
 * Note: Aman untuk development dan low traffic.
 * Untuk production high traffic, sebaiknya gunakan Redis atau reverse proxy (Nginx/HAProxy).
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $limit;
    private int $window;
    private ?string $prefix;
    private string $storagePath;

    public function __construct(int $limit = 60, int $window = 60, ?string $prefix = null)
    {
        $this->limit = $limit;
        $this->window = $window;
        $this->prefix = $prefix;
        $this->storagePath = dirname(__DIR__, 2) . '/storage/framework/rate-limit';
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }

        // Use REMOTE_ADDR strictly. Do not trust X-Forwarded-For implicitly.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $method = $request->method();
        $uri = $request->uri();
        
        $group = $this->prefix ?? ($method . ':' . $uri);
        
        // Extract normalized email if present (useful for login/register rate limiting)
        $emailPart = '';
        $body = (string) $request->body();
        if (!empty($body)) {
            $decodedBody = json_decode($body, true);
            if (is_array($decodedBody) && !empty($decodedBody['email']) && is_string($decodedBody['email'])) {
                // Hash normalized email to avoid plaintext storage
                $emailPart = '|email:' . hash('sha256', strtolower(trim($decodedBody['email'])));
            }
        }
        
        // Stable, collision-resistant key
        $keyString = "ip:{$ip}|group:{$group}{$emailPart}";
        // Use sha256 to ensure safe filename without path traversal risks
        $key = hash('sha256', $keyString);
        $file = $this->storagePath . '/' . $key . '.json';
        
        $currentTime = time();
        $data = ['hits' => 0, 'reset_at' => $currentTime + $this->window];

        $fp = @fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            if ($content) {
                $decoded = json_decode($content, true);
                // Handle fail-safe: if corrupt, start fresh. If expired, start fresh.
                if (is_array($decoded) && isset($decoded['hits'], $decoded['reset_at'])) {
                    if ($currentTime < (int) $decoded['reset_at']) {
                        $data = [
                            'hits' => (int) $decoded['hits'],
                            'reset_at' => (int) $decoded['reset_at']
                        ];
                    }
                }
            }

            $data['hits']++;

            $remaining = max(0, $this->limit - $data['hits']);
            $headers = [
                'RateLimit-Limit' => (string) $this->limit,
                'RateLimit-Remaining' => (string) $remaining,
                'RateLimit-Reset' => (string) $data['reset_at'],
            ];

            if ($data['hits'] > $this->limit) {
                $retryAfter = max(0, $data['reset_at'] - $currentTime);
                $headers['Retry-After'] = (string) $retryAfter;
                
                flock($fp, LOCK_UN);
                fclose($fp);
                
                $response = ApiResponse::error('Too many requests', 429, 'RATE_LIMITED');
                foreach ($headers as $k => $v) {
                    $response->header($k, $v);
                }
                return $response;
            }

            // Atomic write approach for file
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            // Fallback if locking fails
            $data['hits']++;
            $remaining = max(0, $this->limit - $data['hits']);
            $headers = [
                'RateLimit-Limit' => (string) $this->limit,
                'RateLimit-Remaining' => (string) $remaining,
                'RateLimit-Reset' => (string) $data['reset_at'],
            ];
            if ($fp) {
                fclose($fp);
            }
        }

        $response = $handler->handle($request);

        // Append rate limit headers to successful response
        foreach ($headers as $k => $v) {
            $response->header($k, $v);
        }

        return $response;
    }
}
