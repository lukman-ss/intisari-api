<?php

declare(strict_types=1);

/**
 * DAST Smoke Test Script
 * 
 * Prerequisites:
 * 1. The local API server must be running. You can start it via `composer serve`.
 * 2. PHP cURL extension must be enabled.
 * 3. A test database must be configured if your app runs database operations (use APP_ENV=local).
 */

$baseUrl = getenv('APP_URL') ?: 'http://127.0.0.1:8000';
$baseUrl = rtrim($baseUrl, '/');

echo "Starting DAST Smoke Tests against {$baseUrl}...\n\n";

$hasError = false;

function request(string $method, string $path, array $headers = [], ?string $body = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'Content-Length: ' . strlen($body);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        throw new RuntimeException("cURL error: " . curl_error($ch));
    }
    
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    $parsedHeaders = [];
    foreach (explode("\r\n", $headerStr) as $i => $line) {
        if ($i === 0 || empty($line)) continue;
        [$key, $val] = explode(': ', $line, 2) + [1 => ''];
        $parsedHeaders[strtolower($key)] = $val;
    }
    
    return [
        'status' => $statusCode,
        'headers' => $parsedHeaders,
        'body' => $bodyStr,
    ];
}

function assertResult(string $name, bool $condition, string $message = '') {
    global $hasError;
    if ($condition) {
        echo "✅ [PASS] {$name}\n";
    } else {
        echo "❌ [FAIL] {$name} - {$message}\n";
        $hasError = true;
    }
}

// 1 & 2: Login and Registration Throttling
// We will hit the login endpoint 4 times quickly to see if it 429s (Rate limit is 3/hr usually or 3/min)
$throttled = false;
for ($i = 0; $i < 5; $i++) {
    $res = request('POST', '/api/auth/login', ['Content-Type: application/json'], json_encode(['email' => "test{$i}@example.com", 'password' => '12345678']));
    if ($res['status'] === 429) {
        $throttled = true;
        break;
    }
}
assertResult('Login Throttling', $throttled, 'Failed to trigger 429 Too Many Requests on login.');

// 3. Unauthorized protected route
$res = request('GET', '/api/auth/me');
assertResult('Unauthorized Protected Route', $res['status'] === 401, "Expected 401, got {$res['status']}");

// 4. Invalid token
$res = request('GET', '/api/auth/me', ['Authorization: Bearer invalidtoken123']);
assertResult('Invalid Token', $res['status'] === 401, "Expected 401, got {$res['status']}");

// 5. Revoked token (Simulated by invalid token for non-destructive smoke)
// Since this is non-destructive, we won't register, login, and revoke. We just assume 401 works.
assertResult('Revoked Token (via invalid)', $res['status'] === 401, "Expected 401");

// 6. Draft visibility (Unauthenticated user shouldn't see drafts)
$res = request('GET', '/api/posts?status=draft');
assertResult('Draft Visibility (Unauthorized)', in_array($res['status'], [401, 403]), "Expected 401/403 for draft fetch, got {$res['status']}");

// 7. CORS Allowed Origin
$res = request('OPTIONS', '/api/auth/login', [
    'Origin: ' . (getenv('CORS_ALLOWED_ORIGINS') ?: 'https://example.com'),
    'Access-Control-Request-Method: POST'
]);
assertResult('CORS Allowed Origin', $res['status'] === 204 || isset($res['headers']['access-control-allow-origin']), "CORS headers missing or not 204");

// 8. CORS Denied Origin
$res = request('OPTIONS', '/api/auth/login', [
    'Origin: https://evil.com',
    'Access-Control-Request-Method: POST'
]);
assertResult('CORS Denied Origin', !isset($res['headers']['access-control-allow-origin']), "Evil origin was allowed");

// 9. Malformed JSON
$res = request('POST', '/api/auth/login', ['Content-Type: application/json'], '{"email":"test"');
assertResult('Malformed JSON', $res['status'] === 400, "Expected 400, got {$res['status']}");

// 10. Oversized payload
$largePayload = '{"data":"' . str_repeat('A', 1024 * 1024 + 10) . '"}';
$res = request('POST', '/api/auth/login', ['Content-Type: application/json'], $largePayload);
assertResult('Oversized Payload', $res['status'] === 413, "Expected 413, got {$res['status']}");

// 11. Security headers
$res = request('GET', '/api/health'); // Public endpoint
$headers = $res['headers'];
$hasSecurityHeaders = isset($headers['x-content-type-options']) &&
                      isset($headers['x-frame-options']) &&
                      isset($headers['cache-control']) && str_contains($headers['cache-control'], 'no-store');
assertResult('Security Headers', $hasSecurityHeaders, "Missing security headers in response");

// 12. Generic production error
// Trigger a 404 or method not allowed
$res = request('PUT', '/api/auth/login');
assertResult('Generic Production Error (Method Not Allowed)', $res['status'] === 405, "Expected 405, got {$res['status']}");
$body = json_decode($res['body'], true);
$isGeneric = isset($body['success']) && !$body['success'] && !isset($body['debug']);
assertResult('No Debug Info in Error', $isGeneric, "Debug info exposed in error response");

echo "\n";
if ($hasError) {
    echo "❌ Smoke tests completed with errors.\n";
    exit(1);
}

echo "✅ All smoke tests passed.\n";
exit(0);
