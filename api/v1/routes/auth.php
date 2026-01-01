<?php
declare(strict_types=1);

use ACA\Api\Controllers\AuthController;

/** @var ACA\Api\Core\Router $router */
$router->post('/auth/login', [AuthController::class, 'login']);
$router->get('/auth/me', [AuthController::class, 'me']);
