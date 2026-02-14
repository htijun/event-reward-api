<?php
declare(strict_types=1);

require __DIR__ . '/../src/Support/Response.php';
require __DIR__ . '/../src/Support/Request.php';
require __DIR__ . '/../src/Support/Router.php';

use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

$router = new Router();

$router->get('/health', function () {
    Response::json(['ok' => true, 'data' => ['status' => 'up']]);
});

$router->dispatch(new Request());
