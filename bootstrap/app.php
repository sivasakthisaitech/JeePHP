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

if (getenv('APP_DEBUG') !== 'true') {
    ini_set('display_errors', '0');
}
