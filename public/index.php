<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/** @var \Intisari\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->run();
