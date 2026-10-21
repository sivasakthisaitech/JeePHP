<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    throw new RuntimeException('JeePHP dependencies are missing. Run composer install in the project folder.');
}

// Load simple KEY=value settings from .duck without requiring another package.
$configFile = $root . '/.duck';
if (is_file($configFile)) {
    foreach (file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

\JeePHP\Debug\DeveloperConsole::boot($root);

if (filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL)) {
    register_shutdown_function([\JeePHP\Database\QueryMonitor::class, 'report']);
}

$appConfig = require $root . '/config/app.php';
$providers = $appConfig['providers'] ?? [];
if (!is_array($providers)) {
    throw new RuntimeException('The providers setting in config/app.php must be an array.');
}

$providerInstances = [];
foreach ($providers as $providerClass) {
    if (!is_string($providerClass) || !class_exists($providerClass)) {
        throw new RuntimeException('Application service provider not found: ' . (string) $providerClass);
    }
    $provider = new $providerClass();
    if (!$provider instanceof \JeePHP\Foundation\ServiceProvider) {
        throw new RuntimeException($providerClass . ' must extend JeePHP\\Foundation\\ServiceProvider.');
    }
    $providerInstances[] = $provider;
}

foreach ($providerInstances as $provider) {
    $provider->register();
}
foreach ($providerInstances as $provider) {
    $provider->boot();
}
