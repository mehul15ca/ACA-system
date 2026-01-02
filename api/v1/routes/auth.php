<?php
declare(strict_types=1);

use ACA\Api\Controllers\AuthController;
use ACA\Api\Controllers\AuthRefreshController;
use ACA\Api\Controllers\AuthLogoutController;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

$router->post('/auth/login',   [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthRefreshController::class, 'refresh']);
$router->post('/auth/logout',  [AuthLogoutController::class, 'logout']);
$router->get('/auth/me',       [AuthController::class, 'me']);
