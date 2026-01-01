<?php
declare(strict_types=1);

use ACA\Api\Core\Response;

/** @var ACA\Api\Core\Router $router */
$router->get('/health', function () {
  Response::ok([
    'service' => 'ACA API',
    'version' => 'v1',
    'time' => date('c'),
  ], 'Healthy');
});
