<?php

declare(strict_types=1);

echo "Starting Security Scan...\n";

$directories = ['app', 'config', 'database', 'routes', 'public'];
$hasError = false;

$regexes = [
    'Private Key' => '/-----BEGIN (RSA|OPENSSH|DSA|EC|PGP) PRIVATE KEY-----/i',
    'AWS Access Key' => '/AKIA[0-9A-Z]{16}/',
    'eval() usage' => '/\beval\s*\(/',
    'unserialize() usage' => '/\bunserialize\s*\(/',
    'shell execution (exec)' => '/(?<!->)(?<!::)\bexec\s*\(/',
    'shell execution (shell_exec)' => '/(?<!->)(?<!::)\bshell_exec\s*\(/',
    'shell execution (system)' => '/(?<!->)(?<!::)\bsystem\s*\(/',
    'shell execution (passthru)' => '/(?<!->)(?<!::)\bpassthru\s*\(/',
    'dynamic include' => '/\b(include|require)(_once)?\s*\(?\s*\$/',
];

foreach ($directories as $dir) {
    $dirPath = realpath(__DIR__ . '/../' . $dir);
    if (!$dirPath || !is_dir($dirPath)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'json', 'yml', 'yaml', 'env'])) continue;

        $content = file_get_contents($file->getPathname());
        if ($content === false) continue;
        
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            if (str_contains($line, '@security-ignore')) {
                continue;
            }
            foreach ($regexes as $name => $regex) {
                if (preg_match($regex, $line)) {
                    echo "🚨 [{$name}] found in {$file->getPathname()} on line " . ($lineNum + 1) . "\n";
                    echo "   " . trim($line) . "\n\n";
                    $hasError = true;
                }
            }
        }
    }
}

// Active Debug Configuration check
$envExample = file_get_contents(__DIR__ . '/../.env.example');
if ($envExample && preg_match('/^APP_DEBUG\s*=\s*true/im', $envExample)) {
    echo "🚨 [Active Debug Config] found in .env.example\n";
    $hasError = true;
}

if ($hasError) {
    echo "❌ Security scan failed. Found secrets or risky patterns.\n";
    exit(1);
}

echo "✅ Security scan passed. No secrets or risky patterns found.\n";
exit(0);
