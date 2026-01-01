<?php
use ACA\Api\Controllers\SalaryController;

/** @var ACA\Api\Core\Router $router */
$router->get('/salaries', [SalaryController::class, 'index']);
