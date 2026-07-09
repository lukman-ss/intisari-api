<?php

declare(strict_types=1);

use Lukman\Console\Input;
use Lukman\Console\Output;

/** @var \Intisari\Application $app */

$app->command('env:check', function (Input $input, Output $output) use ($app) {
    $output->writeln("Validating environment variables...\n");
    
    $errors = 0;
    
    $check = function(string $name, bool $valid) use ($output, &$errors) {
        if ($valid) {
            $output->writeln("[OK] {$name}");
        } else {
            $output->writeln("[FAILED] {$name}");
            $errors++;
        }
    };
    
    $check('APP_NAME is required', !empty(getenv('APP_NAME')));
    $check('APP_ENV is required', !empty(getenv('APP_ENV')));
    
    $debug = getenv('APP_DEBUG');
    $check('APP_DEBUG must be boolean-like', in_array(strtolower((string)$debug), ['true', 'false', '1', '0', ''], true));
    
    $dbConn = getenv('DB_CONNECTION');
    $check('DB_CONNECTION is required', !empty($dbConn));
    
    if ($dbConn === 'sqlite') {
        $dbPath = getenv('DB_DATABASE');
        if (!$dbPath) {
            $dbPath = $app->basePath('database/api.sqlite');
        } else if (!str_starts_with($dbPath, '/') && !str_contains($dbPath, ':\\')) {
            $dbPath = $app->basePath($dbPath);
        }

        $check('DB_DATABASE is defined for sqlite', !empty($dbPath));
        
        $dbDir = dirname($dbPath);
        $check('Database directory is writable', is_dir($dbDir) && is_writable($dbDir));
    }
    
    $logPath = $app->basePath('storage/logs');
    $check('storage/logs is writable', is_dir($logPath) && is_writable($logPath));
    
    $output->writeln("");
    
    if ($errors > 0) {
        $output->writeln("Environment validation failed with {$errors} error(s).");
        return 1;
    }
    
    $output->writeln("Environment is fully valid.");
    return 0;
});

$app->command('migrate', function (Input $input, Output $output) use ($app) {
    $pdo = \App\Database\ConnectionFactory::make();
    $runner = new \App\Database\MigrationRunner($pdo);
    
    $path = $app->basePath('database/migrations');
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    
    $executed = $runner->run($path);
    
    if (empty($executed)) {
        $output->writeln("Nothing to migrate.");
    } else {
        foreach ($executed as $mig) {
            $output->writeln("Migrated: {$mig}");
        }
    }
    
    return 0;
});

$app->command('migrate:fresh', function (Input $input, Output $output) use ($app) {
    global $argv;
    $force = in_array('--force', $argv ?? [], true);
    
    if (!$force && getenv('APP_ENV') !== 'testing') {
        $output->writeln("ERROR: You must use --force to run migrate:fresh in non-testing environment.");
        return 1;
    }

    $pdo = \App\Database\ConnectionFactory::make();
    $runner = new \App\Database\MigrationRunner($pdo);
    
    $path = $app->basePath('database/migrations');
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    
    $executed = $runner->fresh($path);
    $count = count($executed);
    
    $output->writeln("Dropped all tables successfully.");
    $output->writeln("Executed {$count} migration(s).");
    
    return 0;
});

$app->command('db:seed', function (Input $input, Output $output) {
    if (getenv('APP_ENV') === 'production') {
        $output->writeln("ERROR: Cannot run seeders in production environment.");
        return 1;
    }

    $pdo = \App\Database\ConnectionFactory::make();
    
    if (class_exists(\Database\Seeders\DatabaseSeeder::class)) {
        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run($pdo);
        $output->writeln("Database seeding completed successfully.");
    } else {
        $output->writeln("DatabaseSeeder class not found.");
        return 1;
    }
    
    return 0;
});
