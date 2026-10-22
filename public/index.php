<?php
declare(strict_types=1);

use JeePHP\Http\Request;
use JeePHP\Routing\Router;
use JeePHP\Security\Security;

require dirname(__DIR__) . '/bootstrap/app.php';

Security::headers();
try {
    $request = Request::capture();
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    exit;
}
if (!Security::verifyCsrf($request)) {
    http_response_code(419);
    header('Content-Type: text/plain; charset=utf-8');
    echo '419 - Request verification failed. Reload the page and try again.';
    exit;
}

$router = new Router();
require dirname(__DIR__) . '/app/routes.php';
require dirname(__DIR__) . '/routes/api.php';
$router->dispatch($request);
