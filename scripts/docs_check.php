<?php

$files = ['docs/openapi.yaml', 'README.md'];
$missing = [];

foreach ($files as $f) {
    if (!file_exists($f)) {
        $missing[] = $f;
        echo 'Missing ' . $f . PHP_EOL;
    }
}

if (!empty($missing)) {
    exit(1);
}

echo 'Docs OK' . PHP_EOL;
exit(0);
