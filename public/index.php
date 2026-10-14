<?php
declare(strict_types=1);

use JeePHP\Http\Request;
use JeePHP\Routing\Router;

require dirname(__DIR__) . '/bootstrap/app.php';

$router = new Router();
require dirname(__DIR__) . '/app/routes.php';
$router->dispatch(Request::capture());
