<?php
declare(strict_types=1);

// Development router used by `php jeep serve`. Static files inside public/
// are served directly; all application URLs go through the JeePHP front controller.
$publicRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'public');
if ($publicRoot === false) {
    http_response_code(500);
    exit('JeePHP public directory is missing.');
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = is_string($uriPath) ? rawurldecode($uriPath) : '/';
$relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uriPath), DIRECTORY_SEPARATOR);
$requestedFile = realpath($publicRoot . DIRECTORY_SEPARATOR . $relativePath);
$insidePublic = $requestedFile !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR);
$isDotFile = preg_match('~(?:^|[\\/])\.[^\\/]*~', $relativePath) === 1;

if ($insidePublic && is_file($requestedFile) && !$isDotFile
    && strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}

require $publicRoot . DIRECTORY_SEPARATOR . 'index.php';
