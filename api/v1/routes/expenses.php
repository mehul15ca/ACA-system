<?php
use ACA\Api\Controllers\ExpenseController;

/** @var ACA\Api\Core\Router $router */
$router->get('/expenses', [ExpenseController::class, 'index']);
