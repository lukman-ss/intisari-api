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
    private string $storagePath;

    public function __construct(int $limit = 60, int $window = 60)
    {
        $this->limit = $limit;
        $this->window = $window;
        $this->storagePath = dirname(__DIR__, 2) . '/storage/framework/rate-limit';
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        // Extract route group by using the first segment of URI
        $uri = $request->uri();
        $segments = explode('/', trim($uri, '/'));
        $group = $segments[0] ?? 'default';
        
        $key = md5($ip . '|' . $group);
        $file = $this->storagePath . '/' . $key . '.json';
        
        $currentTime = time();
        $data = ['hits' => 0, 'reset_at' => $currentTime + $this->window];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['hits'], $decoded['reset_at'])) {
                    if ($currentTime < $decoded['reset_at']) {
                        $data = $decoded;
                    }
                }
            }
        }

        $data['hits']++;

        $remaining = max(0, $this->limit - $data['hits']);
        $headers = [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) $remaining,
        ];

        if ($data['hits'] > $this->limit) {
            $retryAfter = max(0, $data['reset_at'] - $currentTime);
            $headers['Retry-After'] = (string) $retryAfter;
            
            $response = ApiResponse::error('Too many requests', 429, 'RATE_LIMITED');
            foreach ($headers as $k => $v) {
                $response->header($k, $v);
            }
            return $response;
        }

        @file_put_contents($file, json_encode($data), LOCK_EX);

        $response = $handler->handle($request);

        // Append rate limit headers to successful response
        foreach ($headers as $k => $v) {
            $response->header($k, $v);
        }

        return $response;
    }
}
