<?php

use ACA\Api\Controllers\AuthRefreshController;
use ACA\Api\Controllers\AuthLogoutController;

/*
|--------------------------------------------------------------------------
| Auth – Token Lifecycle
|--------------------------------------------------------------------------
*/

$router->post('/auth/refresh', [AuthRefreshController::class, 'refresh']);
$router->post('/auth/logout',  [AuthLogoutController::class, 'logout']);
