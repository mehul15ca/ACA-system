<?php
use ACA\Api\Controllers\AttendanceController;

/** @var ACA\Api\Core\Router $router */
$router->get('/attendance', [AttendanceController::class, 'index']);
